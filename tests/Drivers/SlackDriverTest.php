<?php

namespace Tests\Drivers;

use PHPUnit\Framework\TestCase;
use TusharKhan\Chatbot\Drivers\SlackDriver;
use TusharKhan\Chatbot\Storage\FileStore;
use TusharKhan\Chatbot\Core\Bot;

class SlackDriverTest extends TestCase
{
    private string $botToken = 'xoxb-test-token';
    private string $signingSecret = 'test-signing-secret';
    private string $tempStoragePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempStoragePath = sys_get_temp_dir() . '/chatbot-test-' . uniqid();
        if (!file_exists($this->tempStoragePath)) {
            mkdir($this->tempStoragePath, 0755, true);
        }
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempStoragePath)) {
            $this->removeDirectory($this->tempStoragePath);
        }
        parent::tearDown();
    }

    private function removeDirectory(string $dir): void
    {
        if (is_dir($dir)) {
            $files = array_diff(scandir($dir), ['.', '..']);
            foreach ($files as $file) {
                $path = $dir . DIRECTORY_SEPARATOR . $file;
                is_dir($path) ? $this->removeDirectory($path) : unlink($path);
            }
            rmdir($dir);
        }
    }

    public function testSlackDriverInstantiation()
    {
        $driver = new SlackDriver($this->botToken, $this->signingSecret, []);

        $this->assertInstanceOf(SlackDriver::class, $driver);
        $this->assertNull($driver->getMessage());
        $this->assertNull($driver->getSenderId());
        $this->assertNull($driver->getChannelId());
    }

    public function testParseRegularMessage()
    {
        $eventData = [
            'type' => 'event_callback',
            'event' => [
                'type' => 'message',
                'text' => 'Hello bot!',
                'user' => 'U1234567890',
                'channel' => 'C1234567890',
                'ts' => '1234567890.123456'
            ]
        ];

        $driver = new SlackDriver($this->botToken, null, $eventData);

        $this->assertEquals('Hello bot!', $driver->getMessage());
        $this->assertEquals('U1234567890', $driver->getSenderId());
        $this->assertEquals('C1234567890', $driver->getChannelId());
    }

    public function testParseSlashCommand()
    {
        $eventData = [
            'command' => '/weather',
            'text' => 'London',
            'user_id' => 'U1234567890',
            'channel_id' => 'C1234567890'
        ];

        $driver = new SlackDriver($this->botToken, null, $eventData);

        $this->assertEquals('/weather London', $driver->getMessage());
        $this->assertEquals('U1234567890', $driver->getSenderId());
        $this->assertEquals('C1234567890', $driver->getChannelId());
        $this->assertTrue($driver->isSlashCommand());
    }

    public function testParseAppMention()
    {
        $eventData = [
            'type' => 'event_callback',
            'event' => [
                'type' => 'app_mention',
                'text' => '<@U0LAN0Z89> hello there!',
                'user' => 'U1234567890',
                'channel' => 'C1234567890'
            ]
        ];

        $driver = new SlackDriver($this->botToken, null, $eventData);

        $this->assertEquals('<@U0LAN0Z89> hello there!', $driver->getMessage());
        $this->assertEquals('U1234567890', $driver->getSenderId());
        $this->assertEquals('C1234567890', $driver->getChannelId());
        $this->assertTrue($driver->isMention());
    }

    public function testParseButtonInteraction()
    {
        $payload = [
            'type' => 'block_actions',
            'user' => ['id' => 'U1234567890'],
            'channel' => ['id' => 'C1234567890'],
            'actions' => [
                [
                    'action_id' => 'check_weather',
                    'value' => 'weather_check'
                ]
            ]
        ];

        $eventData = [
            'payload' => json_encode($payload)
        ];

        $driver = new SlackDriver($this->botToken, null, $eventData);

        $this->assertEquals('action:check_weather:weather_check', $driver->getMessage());
        $this->assertEquals('U1234567890', $driver->getSenderId());
        $this->assertEquals('C1234567890', $driver->getChannelId());
    }

    public function testIgnoreBotMessages()
    {
        $eventData = [
            'type' => 'event_callback',
            'event' => [
                'type' => 'message',
                'text' => 'This is from another bot',
                'user' => 'U1234567890',
                'channel' => 'C1234567890',
                'bot_id' => 'B1234567890' // This indicates it's from a bot
            ]
        ];

        $driver = new SlackDriver($this->botToken, null, $eventData);

        // Bot messages should be ignored, so message should be null
        $this->assertNull($driver->getMessage());
    }

    public function testHandleReactions()
    {
        $eventData = [
            'type' => 'event_callback',
            'event' => [
                'type' => 'reaction_added',
                'user' => 'U1234567890',
                'reaction' => 'thumbsup',
                'item' => [
                    'type' => 'message',
                    'channel' => 'C1234567890',
                    'ts' => '1234567890.123456'
                ]
            ]
        ];

        $driver = new SlackDriver($this->botToken, null, $eventData);

        $this->assertEquals('reaction_added:thumbsup', $driver->getMessage());
        $this->assertEquals('U1234567890', $driver->getSenderId());
        $this->assertEquals('C1234567890', $driver->getChannelId());
    }

    public function testMultipleCommandPatterns()
    {
        // Test different command patterns
        $testCases = [
            [
                'eventData' => [
                    'command' => '/task',
                    'text' => 'add Buy groceries',
                    'user_id' => 'U1234567890',
                    'channel_id' => 'C1234567890'
                ],
                'expectedMessage' => '/task add Buy groceries'
            ],
            [
                'eventData' => [
                    'command' => '/task',
                    'text' => 'list',
                    'user_id' => 'U1234567890',
                    'channel_id' => 'C1234567890'
                ],
                'expectedMessage' => '/task list'
            ]
        ];

        foreach ($testCases as $testCase) {
            $driver = new SlackDriver($this->botToken, null, $testCase['eventData']);

            $this->assertEquals($testCase['expectedMessage'], $driver->getMessage());
            $this->assertEquals('U1234567890', $driver->getSenderId());
            $this->assertEquals('C1234567890', $driver->getChannelId());
        }
    }

    public function testComplexMessageHandling()
    {
        // Test complex message with emojis and mentions
        $eventData = [
            'type' => 'event_callback',
            'event' => [
                'type' => 'message',
                'text' => 'Hey <@U0LAN0Z89> 👋 can you help me with weather in New York? 🌤️',
                'user' => 'U1234567890',
                'channel' => 'C1234567890'
            ]
        ];

        $driver = new SlackDriver($this->botToken, null, $eventData);

        $expectedMessage = 'Hey <@U0LAN0Z89> 👋 can you help me with weather in New York? 🌤️';
        $this->assertEquals($expectedMessage, $driver->getMessage());
        $this->assertEquals('U1234567890', $driver->getSenderId());
        $this->assertEquals('C1234567890', $driver->getChannelId());
    }

    public function testDriverHelperMethods()
    {
        // Test isDirectMessage
        $dmEventData = [
            'type' => 'event_callback',
            'event' => [
                'type' => 'message',
                'text' => 'Hello',
                'user' => 'U1234567890',
                'channel' => 'D1234567890' // DM channels start with D
            ]
        ];

        $driver = new SlackDriver($this->botToken, null, $dmEventData);
        $this->assertTrue($driver->isDirectMessage());

        // Test getEventType
        $this->assertEquals('message', $driver->getEventType());

        // Test hasMessage
        $this->assertTrue($driver->hasMessage());
    }

    public function testSlashCommandDetection()
    {
        $eventData = [
            'command' => '/status',
            'text' => '',
            'user_id' => 'U1234567890',
            'channel_id' => 'C1234567890'
        ];

        $driver = new SlackDriver($this->botToken, null, $eventData);

        $this->assertTrue($driver->isSlashCommand());
        $this->assertEquals('/status ', $driver->getMessage());
    }
}
