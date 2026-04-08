<?php declare(strict_types=1);

namespace Danilovl\LogViewerBundle\Tests\Unit\Action\Api;

use Danilovl\LogViewerBundle\Action\Api\GetFileContentAction;
use Danilovl\LogViewerBundle\DTO\LogViewerSource;
use Danilovl\LogViewerBundle\Parser\Reader\{
    LogSourceManager,
    LogViewer
};
use PHPUnit\Framework\MockObject\{
    Stub
};
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;

class GetFileContentActionTest extends TestCase
{
    private LogSourceManager&Stub $sourceManager;

    private LogViewer&Stub $logViewer;

    private GetFileContentAction $action;

    protected function setUp(): void
    {
        $this->sourceManager = $this->createStub(LogSourceManager::class);
        $this->sourceManager->method('isWithinAllowedDirs')->willReturn(true);
        $this->logViewer = $this->createStub(LogViewer::class);
        $this->action = new GetFileContentAction($this->sourceManager, $this->logViewer);
    }

    public function testInvokeSourceNotFound(): void
    {
        $this->sourceManager->method('getSourceById')->willReturn(null);

        $response = $this->action->__invoke('unknown');

        $this->assertEquals(JsonResponse::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testInvokeFileNotReadable(): void
    {
        $source = new LogViewerSource(
            id: 'test',
            name: 'test',
            path: '/tmp/non-existent-file',
            host: null,
            parserType: 'test',
            isValid: true,
            isEmpty: false,
            isTooLarge: false,
            canDelete: true,
            isDeletable: true,
            canDownload: true,
            isDownloadable: true,
            isReadable: false,
            size: 100,
            modified: date('Y-m-d H:i:s')
        );

        $this->sourceManager->method('getSourceById')->willReturn($source);

        $response = $this->action->__invoke('test');

        $this->assertEquals(JsonResponse::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    public function testInvokeSuccess(): void
    {
        $filePath = tempnam(sys_get_temp_dir(), 'log');
        file_put_contents($filePath, "line1\nline2\nline3");

        $source = new LogViewerSource(
            id: 'test',
            name: 'test',
            path: $filePath,
            host: null,
            parserType: 'test',
            isValid: true,
            isEmpty: false,
            isTooLarge: false,
            canDelete: true,
            isDeletable: true,
            canDownload: true,
            isDownloadable: true,
            isReadable: true,
            size: 100,
            modified: date('Y-m-d H:i:s')
        );

        $this->sourceManager->method('getSourceById')->willReturn($source);
        $this->logViewer->method('getFileContent')->willReturn([
            'lines' => ['line1', 'line2'],
            'page' => 1,
            'limit' => 2,
            'totalLines' => 3
        ]);

        $response = $this->action->__invoke('test', 1, 2);
        /** @var array<string, mixed> $data */
        $data = json_decode((string) $response->getContent(), true);

        $this->assertEquals(JsonResponse::HTTP_OK, $response->getStatusCode());
        $this->assertEquals(['line1', 'line2'], $data['lines']);
        $this->assertEquals(1, $data['page']);
        $this->assertEquals(2, $data['limit']);
        $this->assertEquals(3, $data['totalLines']);

        unlink($filePath);
    }

    public function testInvokeSuccessWithLine(): void
    {
        $filePath = tempnam(sys_get_temp_dir(), 'log');
        file_put_contents($filePath, "line1\nline2\nline3\nline4\nline5");

        $source = new LogViewerSource(
            id: 'test',
            name: 'test',
            path: $filePath,
            host: null,
            parserType: 'test',
            isValid: true,
            isEmpty: false,
            isTooLarge: false,
            canDelete: true,
            isDeletable: true,
            canDownload: true,
            isDownloadable: true,
            isReadable: true,
            size: 100,
            modified: date('Y-m-d H:i:s')
        );

        $this->sourceManager->method('getSourceById')->willReturn($source);
        // Page 1, limit 2, jump to line 3 (index 2)
        $this->logViewer->method('getFileContent')->willReturn([
            'lines' => ['line3', 'line4'],
            'page' => 2,
            'limit' => 2,
            'totalLines' => 5
        ]);

        $response = $this->action->__invoke('test', 1, 2, 2);
        /** @var array<string, mixed> $data */
        $data = json_decode((string) $response->getContent(), true);

        $this->assertEquals(JsonResponse::HTTP_OK, $response->getStatusCode());
        $this->assertEquals(['line3', 'line4'], $data['lines']);
        // (line 2 + count 2) / limit 2 = 2. Floor(2) + 1 = 3? 
        // 0, 1 -> page 1
        // 2, 3 -> page 2
        // 4 -> page 3
        // Line index 2 is in page 2.
        // My logic in action: (int) floor(($line + count($lines)) / $limit) + 1;
        // (2 + 2) / 2 = 2. Floor(2) + 1 = 3. 
        // Wait, if limit is 2, lines are [0, 1], [2, 3], [4].
        // Line index 2 is the start of the second page. 
        // (2 + 2) / 2 = 2. 
        // Maybe the logic should be: floor($line / $limit) + 1
        // 2 / 2 = 1. 1 + 1 = 2. Correct.

        $this->assertEquals(2, $data['page']);

        unlink($filePath);
    }
}
