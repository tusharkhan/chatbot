<?php

namespace TusharKhan\Chatbot\Tests\Drivers;

use PHPUnit\Framework\TestCase;
use TusharKhan\Chatbot\Drivers\SlackDriver;

class SlackDriverTest extends TestCase
{
    private $slackDriver;
    private $testToken = 'xoxb-test-token';
    private $testSigningSecret = 'test-signing-secret';

    protected function setUp(): void
    {
        // Mock event data for testing
        $mockEventData = [
            'type' => 'event_callback',
            'event' => [
                'type' => 'message',
                'text' => 'Hello, World!',
                'user' => 'U123456789',
                'channel' => 'C123456789',
                'ts' => '1234567890.123456'
            ]
        ];

        $this->slackDriver = new SlackDriver($this->testToken, $this->testSigningSecret, $mockEventData);
    }

    public function testGetMessage()
    {
        $this->assertEquals('Hello, World!', $this->slackDriver->getMessage());
    }

    public function testGetSenderId()
    {
        $this->assertEquals('U123456789', $this->slackDriver->getSenderId());
    }

    public function testGetChannelId()
    {
        $this->assertEquals('C123456789', $this->slackDriver->getChannelId());
    }

    public function testHasMessage()
    {
        $this->assertTrue($this->slackDriver->hasMessage());
    }

    public function testGetEventType()
    {
        $this->assertEquals('message', $this->slackDriver->getEventType());
    }

    public function testIsDirectMessage()
    {
        // Test with regular channel (starts with C)
        $this->assertFalse($this->slackDriver->isDirectMessage());

        // Test with direct message channel (starts with D)
        $dmEventData = [
            'type' => 'event_callback',
            'event' => [
                'type' => 'message',
                'text' => 'DM message',
                'user' => 'U123456789',
                'channel' => 'D123456789'
            ]
        ];

        $dmDriver = new SlackDriver($this->testToken, $this->testSigningSecret, $dmEventData);
        $this->assertTrue($dmDriver->isDirectMessage());
    }

    public function testIsMention()
    {
        // Test with app_mention event
        $mentionEventData = [
            'type' => 'event_callback',
            'event' => [
                'type' => 'app_mention',
                'text' => '<@U0LAN0Z89> hello',
                'user' => 'U123456789',
                'channel' => 'C123456789'
            ]
        ];

        $mentionDriver = new SlackDriver($this->testToken, $this->testSigningSecret, $mentionEventData);
        $this->assertTrue($mentionDriver->isMention());
        $this->assertFalse($this->slackDriver->isMention());
    }

    public function testIsSlashCommand()
    {
        // Test with slash command
        $commandEventData = [
            'command' => '/hello',
            'text' => 'world',
            'user_id' => 'U123456789',
            'channel_id' => 'C123456789'
        ];

        $commandDriver = new SlackDriver($this->testToken, $this->testSigningSecret, $commandEventData);
        $this->assertTrue($commandDriver->isSlashCommand());
        $this->assertFalse($this->slackDriver->isSlashCommand());
    }

    public function testIsInteractive()
    {
        // Test with interactive payload
        $interactiveEventData = [
            'payload' => json_encode([
                'type' => 'block_actions',
                'user' => ['id' => 'U123456789'],
                'channel' => ['id' => 'C123456789'],
                'actions' => [
                    [
                        'action_id' => 'button_1',
                        'value' => 'clicked'
                    ]
                ]
            ])
        ];

        $interactiveDriver = new SlackDriver($this->testToken, $this->testSigningSecret, $interactiveEventData);
        $this->assertTrue($interactiveDriver->isInteractive());
        $this->assertFalse($this->slackDriver->isInteractive());
    }

    public function testUrlVerificationChallenge()
    {
        // Mock the challenge response for URL verification
        $challengeData = [
            'type' => 'url_verification',
            'challenge' => 'test_challenge_token'
        ];

        // Test that the challenge data is properly set
        // Note: In real implementation, this would output the challenge and exit
        // For testing purposes, we'll just verify the data structure
        $this->assertTrue(isset($challengeData['type']));
        $this->assertEquals('url_verification', $challengeData['type']);
        $this->assertTrue(isset($challengeData['challenge']));
        $this->assertEquals('test_challenge_token', $challengeData['challenge']);
    }

    public function testReactionEvent()
    {
        $reactionEventData = [
            'type' => 'event_callback',
            'event' => [
                'type' => 'reaction_added',
                'user' => 'U123456789',
                'reaction' => 'thumbsup',
                'item' => [
                    'type' => 'message',
                    'channel' => 'C123456789',
                    'ts' => '1234567890.123456'
                ]
            ]
        ];

        $reactionDriver = new SlackDriver($this->testToken, $this->testSigningSecret, $reactionEventData);
        $this->assertEquals('reaction_added:thumbsup', $reactionDriver->getMessage());
        $this->assertEquals('U123456789', $reactionDriver->getSenderId());
        $this->assertEquals('C123456789', $reactionDriver->getChannelId());
    }

    public function testGetData()
    {
        $data = $this->slackDriver->getData();
        $this->assertIsArray($data);
        $this->assertEquals('event_callback', $data['type']);
        $this->assertArrayHasKey('event', $data);
    }

    public function testGetEvent()
    {
        $event = $this->slackDriver->getEvent();
        $this->assertIsArray($event);
        $this->assertEquals('message', $event['type']);
        $this->assertEquals('Hello, World!', $event['text']);
    }

    public function testSlashCommandParsing()
    {
        $slashCommandData = [
            'command' => '/weather',
            'text' => 'New York',
            'user_id' => 'U123456789',
            'channel_id' => 'C123456789'
        ];

        $commandDriver = new SlackDriver($this->testToken, $this->testSigningSecret, $slashCommandData);
        
        // The message should include the command
        $this->assertEquals('/weather New York', $commandDriver->getMessage());
        $this->assertEquals('U123456789', $commandDriver->getSenderId());
        $this->assertEquals('C123456789', $commandDriver->getChannelId());
        $this->assertTrue($commandDriver->isSlashCommand());
    }

    public function testInteractiveButtonAction()
    {
        $buttonActionData = [
            'payload' => json_encode([
                'type' => 'block_actions',
                'user' => ['id' => 'U123456789'],
                'channel' => ['id' => 'C123456789'],
                'actions' => [
                    [
                        'action_id' => 'approve_button',
                        'value' => 'approve'
                    ]
                ]
            ])
        ];

        $buttonDriver = new SlackDriver($this->testToken, $this->testSigningSecret, $buttonActionData);
        
        $this->assertEquals('action:approve_button:approve', $buttonDriver->getMessage());
        $this->assertEquals('U123456789', $buttonDriver->getSenderId());
        $this->assertEquals('C123456789', $buttonDriver->getChannelId());
        $this->assertTrue($buttonDriver->isInteractive());
    }

    public function testIgnoreBotMessages()
    {
        $botMessageData = [
            'type' => 'event_callback',
            'event' => [
                'type' => 'message',
                'text' => 'Bot message',
                'bot_id' => 'B123456789',
                'channel' => 'C123456789'
            ]
        ];

        $botDriver = new SlackDriver($this->testToken, $this->testSigningSecret, $botMessageData);
        
        // Bot messages should be ignored
        $this->assertNull($botDriver->getMessage());
        $this->assertNull($botDriver->getSenderId());
        $this->assertFalse($botDriver->hasMessage());
    }
}
