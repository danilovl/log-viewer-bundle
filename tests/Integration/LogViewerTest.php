<?php declare(strict_types=1);

namespace Danilovl\LogViewerBundle\Tests\Integration;

use Danilovl\LogViewerBundle\Parser\Reader\LogViewer;
use Danilovl\LogViewerBundle\Tests\LogPathTrait;
use Danilovl\LogViewerBundle\Tests\Mock\KernelTest;
use PHPUnit\Framework\Attributes\DataProvider;
use Generator;

final class LogViewerTest extends KernelTest
{
    use LogPathTrait;

    private LogViewer $viewer;

    protected function setUp(): void
    {
        $logDir = $this->getMockDir();

        $this->bootKernel([
            'sources' => [
                'dirs' => [$logDir]
            ]
        ]);

        /** @var LogViewer $viewer */
        $viewer = $this->container->get(LogViewer::class);
        $this->viewer = $viewer;
    }

    #[DataProvider('parserDataProvider')]
    public function testGetEntries(string $filename, string $parserType): void
    {
        $logPath = $this->getLogPath($filename);
        $entries = $this->viewer->getEntries($logPath, $parserType);

        $this->assertNotEmpty($entries);
    }

    #[DataProvider('parserDataProvider')]
    public function testGetStats(string $filename, string $parserType): void
    {
        $logPath = $this->getLogPath($filename);
        $stats = $this->viewer->getStats($logPath, $parserType);

        $this->assertGreaterThan(0, $stats->total);
    }

    #[DataProvider('parserDataProvider')]
    public function testGetCount(string $filename, string $parserType): void
    {
        $logPath = $this->getLogPath($filename);
        $count = $this->viewer->getCount($logPath, $parserType);

        $this->assertGreaterThan(0, $count);
    }

    public function testLargeLogFile(): void
    {
        $largeLogPath = sys_get_temp_dir() . '/large_test.log';
        $handle = fopen($largeLogPath, 'wb');
        if ($handle === false) {
            $this->fail('Could not create temp file');
        }

        for ($i = 0; $i < 1_000; $i++) {
            $date = '2026-03-29T09:44:14.945778+00:00';
            $line = "[$date] app.INFO: test message $i [] []\n";
            fwrite($handle, $line);
        }

        fclose($handle);

        try {
            $stats = $this->viewer->getStats($largeLogPath, 'monolog');
            $this->assertSame(1_000, $stats->total);

            $entries = $this->viewer->getEntries($largeLogPath, 'monolog', null, 50);
            $this->assertCount(50, $entries);
        } finally {
            unlink($largeLogPath);
        }
    }

    public function testEmptyLogFile(): void
    {
        $emptyLogPath = sys_get_temp_dir() . '/empty_test.log';
        file_put_contents($emptyLogPath, '');

        try {
            $stats = $this->viewer->getStats($emptyLogPath, 'monolog');
            $this->assertSame(0, $stats->total);
            $this->assertSame(0, $stats->size);

            $entries = $this->viewer->getEntries($emptyLogPath, 'monolog', null, 50);
            $this->assertCount(0, $entries);
        } finally {
            if (file_exists($emptyLogPath)) {
                unlink($emptyLogPath);
            }
        }
    }

    public static function parserDataProvider(): Generator
    {
        yield ['monolog.log', 'monolog'];
        yield ['doctrine.log', 'doctrine'];
        yield ['php_error.log', 'php'];
        yield ['nginx_access.log', 'nginx'];
        yield ['apache_access.log', 'apache'];
        yield ['syslog.log', 'syslog'];
        yield ['json.log', 'json'];
        yield ['mysql.log', 'mysql'];
        yield ['supervisord.log', 'supervisord'];
        yield ['auth.log', 'auth'];
        yield ['kern.log', 'kern'];
        yield ['php8.4-fpm.log', 'php-fpm'];
        yield ['access.log', 'access'];
    }
}
