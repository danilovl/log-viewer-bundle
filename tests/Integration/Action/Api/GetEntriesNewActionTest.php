<?php declare(strict_types=1);

namespace Danilovl\LogViewerBundle\Tests\Integration\Action\Api;

use Danilovl\LogViewerBundle\Action\Api\GetEntriesNewAction;
use Danilovl\LogViewerBundle\Parser\Reader\{
    LogViewer,
    LogSourceManager
};
use Danilovl\LogViewerBundle\Service\ConfigurationProvider;
use Danilovl\LogViewerBundle\Tests\Mock\KernelTest;
use Symfony\Component\HttpFoundation\{
    Request,
    RequestStack,
    Session\SessionInterface
};

class GetEntriesNewActionTest extends KernelTest
{
    private string $tempLogDir;

    private string $logFile;

    private GetEntriesNewAction $action;

    protected function setUp(): void
    {
        $this->tempLogDir = sys_get_temp_dir() . '/log_viewer_test_' . uniqid();
        mkdir($this->tempLogDir, 0o777, true);

        $this->logFile = $this->tempLogDir . '/test.log';
        file_put_contents($this->logFile, "[2023-01-01 12:00:00] app.DEBUG: Initial line [] []\n");
        clearstatcache(true, $this->logFile);

        $this->bootKernel([
            'sources' => [
                'files' => [$this->logFile]
            ],
            'live_log_page' => [
                'enabled' => true
            ],
            'parser' => [
                'default' => 'monolog'
            ]
        ]);

        /** @var GetEntriesNewAction $action */
        $action = $this->container->get(GetEntriesNewAction::class);
        $this->action = $action;

        $session = $this->createStub(SessionInterface::class);
        $session->method('getId')->willReturn('session_1');

        $request = new Request;
        $request->setSession($session);

        /** @var RequestStack $requestStack */
        $requestStack = $this->container->get(RequestStack::class);
        $requestStack->push($request);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->logFile)) {
            unlink($this->logFile);
        }

        if (is_dir($this->tempLogDir)) {
            rmdir($this->tempLogDir);
        }
    }

    public function testGetEntriesNewWorkflow(): void
    {
        $response = ($this->action)();
        $this->assertEquals(200, $response->getStatusCode());

        $content = (string) $response->getContent();
        /** @var array{entries: array<int, mixed>} $data */
        $data = json_decode($content, true);
        $this->assertCount(0, $data['entries']);

        file_put_contents($this->logFile, "[2023-01-01 12:00:01] app.DEBUG: New error line 2 [] []\n", FILE_APPEND);
        clearstatcache(true, $this->logFile);

        $response = ($this->action)();
        /** @var array{entries: array<int, array{message: string}>} $data */
        $data = json_decode((string) $response->getContent(), true);

        $entries = $data['entries'];
        $this->assertCount(1, $entries);
        $this->assertEquals('New error line 2', $entries[0]['message']);
    }

    public function testGetEntriesNewIsolation(): void
    {
        ($this->action)();

        file_put_contents($this->logFile, "[2023-01-01 12:00:01] app.DEBUG: Isolation test line 2 [] []\n", FILE_APPEND);
        clearstatcache();

        $session2 = $this->createStub(SessionInterface::class);
        $session2->method('getId')->willReturn('session_2');

        $requestStack2 = $this->createStub(RequestStack::class);
        $requestStack2->method('getSession')->willReturn($session2);

        /** @var LogViewer $logViewer */
        $logViewer = $this->container->get(LogViewer::class);
        /** @var LogSourceManager $logSourceManager */
        $logSourceManager = $this->container->get(LogSourceManager::class);
        /** @var ConfigurationProvider $configurationProvider */
        $configurationProvider = $this->container->get(ConfigurationProvider::class);

        $action2 = new GetEntriesNewAction(
            logViewer: $logViewer,
            logSourceManager: $logSourceManager,
            requestStack: $requestStack2
        );

        $response2 = $action2();
        $content2 = (string) $response2->getContent();
        /** @var array{entries: array<int, mixed>} $data2 */
        $data2 = json_decode($content2, true);

        $entries2 = $data2['entries'];
        $this->assertCount(0, $entries2);

        $response1 = ($this->action)();
        $content1 = (string) $response1->getContent();
        /** @var array{entries: array<int, mixed>} $data1 */
        $data1 = json_decode($content1, true);

        $entries1 = $data1['entries'];
        $this->assertCount(1, $entries1);
    }
}
