<?php declare(strict_types=1);

namespace Danilovl\LogViewerBundle\Tests\Unit\Service;

use Danilovl\LogViewerBundle\Service\FileContentReader;
use PHPUnit\Framework\TestCase;

final class FileContentReaderTest extends TestCase
{
    private string $tempFile;

    protected function setUp(): void
    {
        $this->tempFile = tempnam(sys_get_temp_dir(), 'log_viewer_test');
        file_put_contents($this->tempFile, "Line 1\nLine 2\nLine 3\nLine 4\nLine 5");
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempFile)) {
            unlink($this->tempFile);
        }
    }

    public function testReadLines(): void
    {
        $reader = new FileContentReader;

        // Page 1, limit 2
        $lines = $reader->readLines($this->tempFile, 1, 2);
        $this->assertSame(['Line 1', 'Line 2'], $lines);

        // Page 2, limit 2
        $lines = $reader->readLines($this->tempFile, 2, 2);
        $this->assertSame(['Line 3', 'Line 4'], $lines);

        // Page 3, limit 2
        $lines = $reader->readLines($this->tempFile, 3, 2);
        $this->assertSame(['Line 5'], $lines);

        // Page 4, limit 2 (empty)
        $lines = $reader->readLines($this->tempFile, 4, 2);
        $this->assertSame([], $lines);
    }

    public function testGetTotalLines(): void
    {
        $reader = new FileContentReader;
        $this->assertSame(5, $reader->getTotalLines($this->tempFile));
    }
}
