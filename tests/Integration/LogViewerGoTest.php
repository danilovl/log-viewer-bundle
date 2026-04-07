<?php declare(strict_types=1);

namespace Danilovl\LogViewerBundle\Tests\Integration;

use Danilovl\LogViewerBundle\DTO\LogViewerFilters;
use Danilovl\LogViewerBundle\Parser\Reader\LogViewer;
use Danilovl\LogViewerBundle\Tests\LogPathTrait;
use Danilovl\LogViewerBundle\Tests\Mock\KernelTest;
use PHPUnit\Framework\Attributes\DataProvider;
use Generator;

final class LogViewerGoTest extends KernelTest
{
    use LogPathTrait;

    private LogViewer $viewer;

    protected function setUp(): void
    {
        $logDir = $this->getMockDir();
        $goBinaryPath = $this->getGoBinaryPath();

        $this->bootKernel([
            'sources' => [
                'dirs' => [$logDir]
            ],
            'parser' => [
                'go_enabled' => true,
                'go_binary_path' => $goBinaryPath
            ]
        ]);

        /** @var LogViewer $viewer */
        $viewer = $this->container->get(LogViewer::class);
        $this->viewer = $viewer;
    }

    #[DataProvider('parserDataProvider')]
    public function testGetEntriesGo(string $filename, string $parserType): void
    {
        $logPath = $this->getLogPath($filename);
        $entries = $this->viewer->getEntries($logPath, $parserType);

        $this->assertNotEmpty($entries);
    }

    #[DataProvider('parserDataProvider')]
    public function testGetStatsGo(string $filename, string $parserType): void
    {
        $logPath = $this->getLogPath($filename);
        $stats = $this->viewer->getStats($logPath, $parserType);

        $this->assertGreaterThan(0, $stats->total);
    }

    #[DataProvider('parserDataProvider')]
    public function testGetCountGo(string $filename, string $parserType): void
    {
        $logPath = $this->getLogPath($filename);
        $count = $this->viewer->getCount($logPath, $parserType);

        $this->assertGreaterThan(0, $count);
    }

    public function testGetEntriesWithFiltersGo(): void
    {
        $logPath = $this->getLogPath('monolog.log');
        $filters = LogViewerFilters::fromQueryParams(
            level: null,
            channel: null,
            search: null,
            levels: ['CRITICAL']
        );

        $entries = $this->viewer->getEntries($logPath, 'monolog', $filters);

        $this->assertCount(1, $entries);
        $this->assertSame('CRITICAL', $entries[0]->level);
    }

    public function testGetStatsWithDateFiltersGo(): void
    {
        $logPath = $this->getLogPath('monolog.log');
        $filters = LogViewerFilters::fromQueryParams(
            level: null,
            channel: null,
            search: null,
            dateFrom: '2026-03-29 09:44:14',
            dateTo: '2026-03-29 09:44:15'
        );

        $stats = $this->viewer->getStats($logPath, 'monolog', $filters);

        $this->assertSame(2, $stats->total);
    }

    public static function parserDataProvider(): Generator
    {
        yield ['monolog.log', 'monolog'];
        yield ['doctrine.log', 'monolog'];
        yield ['php_error.log', 'php_error'];
        yield ['nginx_access.log', 'nginx_access'];
        yield ['apache_access.log', 'apache_access'];
        yield ['syslog.log', 'syslog'];
        yield ['json.log', 'json'];
        yield ['mysql.log', 'mysql'];
        yield ['supervisord.log', 'supervisord'];
        yield ['auth.log', 'syslog'];
        yield ['kern.log', 'syslog'];
        yield ['php8.4-fpm.log', 'php_fpm'];
        yield ['access.log', 'nginx_access'];
    }
}
