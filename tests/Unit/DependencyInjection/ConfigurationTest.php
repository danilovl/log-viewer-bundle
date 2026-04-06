<?php declare(strict_types=1);

namespace Danilovl\LogViewerBundle\Tests\Unit\DependencyInjection;

use Danilovl\LogViewerBundle\DependencyInjection\Configuration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

class ConfigurationTest extends TestCase
{
    private Processor $processor;

    protected function setUp(): void
    {
        $this->processor = new Processor;
    }

    public function testDefaultChannelsIsEmptyArray(): void
    {
        $config = [
            'notifier' => [
                'rules' => [
                    [
                        'name' => 'test_rule'
                    ]
                ]
            ]
        ];

        $processedConfig = $this->processor->processConfiguration(new Configuration, ['danilovl_log_viewer' => $config]);

        $this->assertEmpty($processedConfig['notifier']['rules'][0]['channels']);
    }

    public function testValidChannelsPass(): void
    {
        $config = [
            'notifier' => [
                'rules' => [
                    [
                        'name' => 'test_rule',
                        'channels' => ['chat/slack', 'email']
                    ]
                ]
            ]
        ];

        $processedConfig = $this->processor->processConfiguration(new Configuration, ['danilovl_log_viewer' => $config]);

        $this->assertEquals(['chat/slack', 'email'], $processedConfig['notifier']['rules'][0]['channels']);
    }

    public function testInvalidChannelFails(): void
    {
        $config = [
            'notifier' => [
                'rules' => [
                    [
                        'name' => 'test_rule',
                        'channels' => ['invalid_channel']
                    ]
                ]
            ]
        ];

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Invalid notifier channel "invalid_channel". Available channels: chat/slack, chat/telegram, email');

        $this->processor->processConfiguration(new Configuration, ['danilovl_log_viewer' => $config]);
    }
}
