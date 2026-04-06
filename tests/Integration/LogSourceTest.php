<?php declare(strict_types=1);

namespace Danilovl\LogViewerBundle\Tests\Integration;

use Danilovl\LogViewerBundle\Parser\Reader\LogSourceManager;
use Danilovl\LogViewerBundle\Tests\LogPathTrait;
use Danilovl\LogViewerBundle\Tests\Mock\KernelTest;
use PHPUnit\Framework\Attributes\DataProvider;
use Generator;

final class LogSourceTest extends KernelTest
{
    use LogPathTrait;

    private LogSourceManager $sourceManager;

    protected function setUp(): void
    {
        $logDir = $this->getMockDir();

        $this->bootKernel([
            'sources' => [
                'dirs' => [$logDir]
            ]
        ]);

        /** @var LogSourceManager $sourceManager */
        $sourceManager = $this->container->get(LogSourceManager::class);
        $this->sourceManager = $sourceManager;
    }

    #[DataProvider('provideIdentifyLogSourceCases')]
    public function testIdentifyLogSource(string $filename, string $expectedParser): void
    {
        $sources = $this->sourceManager->getAllSources();
        $targetSource = null;

        foreach ($sources as $source) {
            if ($source->name === $filename) {
                $targetSource = $source;

                break;
            }
        }

        $this->assertNotNull($targetSource, "Source for $filename not found");
        $this->assertSame($expectedParser, $targetSource->parserType);
    }

    public static function provideIdentifyLogSourceCases(): Generator
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
        yield ['auth.log', 'syslog-modern'];
        yield ['kern.log', 'syslog-modern'];
        yield ['php8.4-fpm.log', 'syslog-modern'];
        yield ['access.log', 'nginx'];
    }
}
