<?php

namespace Tests\Drivers;

use PHPUnit\Framework\TestCase;
use TusharKhan\Chatbot\Drivers\TelegramDriver;

class TelegramDriverTest extends TestCase
{
    private string $testToken = '123456789:ABCDEFGHIJKLMNOPQRSTUVWXYZ';

    public function testConstructorWithoutWebhook()
    {
        $driver = new TelegramDriver($this->testToken);

        $this->assertInstanceOf(TelegramDriver::class, $driver);
        $this->assertNull($driver->getMessage());
        $this->assertNull($driver->getSenderId());
        $this->assertNull($driver->getChatId());
        $this->assertFalse($driver->hasMessage());
    }

    public function testConstructorWithWebhookMessage()
    {
        $webhookData = [
            'update_id' => 123456789,
            'message' => [
                'message_id' => 1,
                'from' => [
                    'id' => 987654321,
                    'is_bot' => false,
                    'first_name' => 'John',
                    'last_name' => 'Doe',
                    'username' => 'johndoe',
                    'language_code' => 'en'
                ],
                'chat' => [
                    'id' => 987654321,
                    'first_name' => 'John',
                    'last_name' => 'Doe',
                    'username' => 'johndoe',
                    'type' => 'private'
                ],
                'date' => 1234567890,
                'text' => 'Hello, bot!'
            ]
        ];

        $driver = new TelegramDriver($this->testToken, $webhookData);

        $this->assertEquals('Hello, bot!', $driver->getMessage());
        $this->assertEquals('987654321', $driver->getSenderId());
        $this->assertEquals('987654321', $driver->getChatId());
        $this->assertTrue($driver->hasMessage());
        $this->assertEquals('message', $driver->getMessageType());

        $userInfo = $driver->getUserInfo();
        $this->assertEquals('John', $userInfo['first_name']);
        $this->assertEquals('Doe', $userInfo['last_name']);
        $this->assertEquals('johndoe', $userInfo['username']);
    }

    public function testCallbackQueryMessage()
    {
        $webhookData = [
            'update_id' => 123456789,
            'callback_query' => [
                'id' => 'callback123',
                'from' => [
                    'id' => 987654321,
                    'is_bot' => false,
                    'first_name' => 'John',
                    'last_name' => 'Doe',
                    'username' => 'johndoe',
                    'language_code' => 'en'
                ],
                'message' => [
                    'message_id' => 2,
                    'from' => [
                        'id' => 123456789,
                        'is_bot' => true,
                        'first_name' => 'TestBot',
                        'username' => 'testbot'
                    ],
                    'chat' => [
                        'id' => 987654321,
                        'first_name' => 'John',
                        'last_name' => 'Doe',
                        'username' => 'johndoe',
                        'type' => 'private'
                    ],
                    'date' => 1234567890,
                    'text' => 'Choose an option:'
                ],
                'data' => 'button_clicked'
            ]
        ];

        $driver = new TelegramDriver($this->testToken, $webhookData);

        $this->assertEquals('button_clicked', $driver->getMessage());
        $this->assertEquals('987654321', $driver->getSenderId());
        $this->assertEquals('987654321', $driver->getChatId());
        $this->assertTrue($driver->hasMessage());
        $this->assertEquals('callback_query', $driver->getMessageType());

        $callbackQuery = $driver->getCallbackQuery();
        $this->assertEquals('callback123', $callbackQuery['id']);
        $this->assertEquals('button_clicked', $callbackQuery['data']);
    }

    public function testPhotoMessage()
    {
        $webhookData = [
            'update_id' => 123456789,
            'message' => [
                'message_id' => 3,
                'from' => [
                    'id' => 987654321,
                    'is_bot' => false,
                    'first_name' => 'John',
                    'username' => 'johndoe'
                ],
                'chat' => [
                    'id' => 987654321,
                    'type' => 'private'
                ],
                'date' => 1234567890,
                'photo' => [
                    [
                        'file_id' => 'photo123',
                        'file_unique_id' => 'unique123',
                        'file_size' => 1024,
                        'width' => 100,
                        'height' => 100
                    ]
                ],
                'caption' => 'Beautiful sunset!'
            ]
        ];

        $driver = new TelegramDriver($this->testToken, $webhookData);

        $this->assertEquals('Beautiful sunset!', $driver->getMessage());
        $this->assertEquals('photo', $driver->getMessageType());

        $photoInfo = $driver->getPhotoInfo();
        $this->assertIsArray($photoInfo);
        $this->assertEquals('photo123', $photoInfo[0]['file_id']);
    }

    public function testDocumentMessage()
    {
        $webhookData = [
            'update_id' => 123456789,
            'message' => [
                'message_id' => 4,
                'from' => [
                    'id' => 987654321,
                    'is_bot' => false,
                    'first_name' => 'John'
                ],
                'chat' => [
                    'id' => 987654321,
                    'type' => 'private'
                ],
                'date' => 1234567890,
                'document' => [
                    'file_id' => 'document123',
                    'file_unique_id' => 'unique123',
                    'file_name' => 'test.pdf',
                    'mime_type' => 'application/pdf',
                    'file_size' => 2048
                ],
                'caption' => 'Important document'
            ]
        ];

        $driver = new TelegramDriver($this->testToken, $webhookData);

        $this->assertEquals('Important document', $driver->getMessage());
        $this->assertEquals('document', $driver->getMessageType());

        $documentInfo = $driver->getDocumentInfo();
        $this->assertIsArray($documentInfo);
        $this->assertEquals('document123', $documentInfo['file_id']);
        $this->assertEquals('test.pdf', $documentInfo['file_name']);
    }

    public function testLocationMessage()
    {
        $webhookData = [
            'update_id' => 123456789,
            'message' => [
                'message_id' => 5,
                'from' => [
                    'id' => 987654321,
                    'is_bot' => false,
                    'first_name' => 'John'
                ],
                'chat' => [
                    'id' => 987654321,
                    'type' => 'private'
                ],
                'date' => 1234567890,
                'location' => [
                    'longitude' => -122.4194,
                    'latitude' => 37.7749
                ]
            ]
        ];

        $driver = new TelegramDriver($this->testToken, $webhookData);

        $this->assertEquals('location', $driver->getMessageType());

        $locationInfo = $driver->getLocationInfo();
        $this->assertIsArray($locationInfo);
        $this->assertEquals(37.7749, $locationInfo['latitude']);
        $this->assertEquals(-122.4194, $locationInfo['longitude']);
    }

    public function testGetData()
    {
        $webhookData = [
            'update_id' => 123456789,
            'message' => [
                'message_id' => 1,
                'from' => ['id' => 987654321],
                'chat' => ['id' => 987654321],
                'date' => 1234567890,
                'text' => 'Test message'
            ]
        ];

        $driver = new TelegramDriver($this->testToken, $webhookData);

        $this->assertEquals($webhookData, $driver->getData());
    }

    public function testGetTelegramApiInstance()
    {
        $driver = new TelegramDriver($this->testToken);

        $api = $driver->getTelegramApi();
        $this->assertInstanceOf(\Telegram\Bot\Api::class, $api);
    }

    public function testGetUpdateObject()
    {
        $webhookData = [
            'update_id' => 123456789,
            'message' => [
                'message_id' => 1,
                'from' => ['id' => 987654321],
                'chat' => ['id' => 987654321],
                'date' => 1234567890,
                'text' => 'Test message'
            ]
        ];

        $driver = new TelegramDriver($this->testToken, $webhookData);

        $update = $driver->getUpdate();
        $this->assertInstanceOf(\Telegram\Bot\Objects\Update::class, $update);
        $this->assertEquals(123456789, $update->getUpdateId());
    }

    public function testGetMessageObject()
    {
        $webhookData = [
            'update_id' => 123456789,
            'message' => [
                'message_id' => 1,
                'from' => ['id' => 987654321, 'first_name' => 'John'],
                'chat' => ['id' => 987654321],
                'date' => 1234567890,
                'text' => 'Test message'
            ]
        ];

        $driver = new TelegramDriver($this->testToken, $webhookData);

        $message = $driver->getMessageObject();
        $this->assertInstanceOf(\Telegram\Bot\Objects\Message::class, $message);
        $this->assertEquals('Test message', $message->getText());
        $this->assertEquals(987654321, $message->getFrom()->getId());
    }

    public function testEditedMessage()
    {
        $webhookData = [
            'update_id' => 123456789,
            'edited_message' => [
                'message_id' => 1,
                'from' => ['id' => 987654321, 'first_name' => 'John'],
                'chat' => ['id' => 987654321],
                'date' => 1234567890,
                'edit_date' => 1234567900,
                'text' => 'Edited message'
            ]
        ];

        $driver = new TelegramDriver($this->testToken, $webhookData);

        $this->assertEquals('Edited message', $driver->getMessage());
        $this->assertEquals('edited_message', $driver->getMessageType());
    }

    public function testVideoMessage()
    {
        $webhookData = [
            'update_id' => 123456789,
            'message' => [
                'message_id' => 6,
                'from' => ['id' => 987654321, 'first_name' => 'John'],
                'chat' => ['id' => 987654321],
                'date' => 1234567890,
                'video' => [
                    'file_id' => 'video123',
                    'file_unique_id' => 'unique123',
                    'width' => 1920,
                    'height' => 1080,
                    'duration' => 60,
                    'file_size' => 5242880
                ],
                'caption' => 'Cool video!'
            ]
        ];

        $driver = new TelegramDriver($this->testToken, $webhookData);

        $this->assertEquals('Cool video!', $driver->getMessage());
        $this->assertEquals('video', $driver->getMessageType());

        $videoInfo = $driver->getVideoInfo();
        $this->assertIsArray($videoInfo);
        $this->assertEquals('video123', $videoInfo['file_id']);
        $this->assertEquals(60, $videoInfo['duration']);
    }

    public function testAudioMessage()
    {
        $webhookData = [
            'update_id' => 123456789,
            'message' => [
                'message_id' => 7,
                'from' => ['id' => 987654321, 'first_name' => 'John'],
                'chat' => ['id' => 987654321],
                'date' => 1234567890,
                'audio' => [
                    'file_id' => 'audio123',
                    'file_unique_id' => 'unique123',
                    'duration' => 180,
                    'performer' => 'Artist',
                    'title' => 'Song Title',
                    'file_size' => 3145728
                ],
                'caption' => 'Great song!'
            ]
        ];

        $driver = new TelegramDriver($this->testToken, $webhookData);

        $this->assertEquals('Great song!', $driver->getMessage());
        $this->assertEquals('audio', $driver->getMessageType());

        $audioInfo = $driver->getAudioInfo();
        $this->assertIsArray($audioInfo);
        $this->assertEquals('audio123', $audioInfo['file_id']);
        $this->assertEquals('Song Title', $audioInfo['title']);
    }

    public function testVoiceMessage()
    {
        $webhookData = [
            'update_id' => 123456789,
            'message' => [
                'message_id' => 8,
                'from' => ['id' => 987654321, 'first_name' => 'John'],
                'chat' => ['id' => 987654321],
                'date' => 1234567890,
                'voice' => [
                    'file_id' => 'voice123',
                    'file_unique_id' => 'unique123',
                    'duration' => 5,
                    'file_size' => 65536
                ]
            ]
        ];

        $driver = new TelegramDriver($this->testToken, $webhookData);

        $this->assertEquals('voice', $driver->getMessageType());

        $voiceInfo = $driver->getVoiceInfo();
        $this->assertIsArray($voiceInfo);
        $this->assertEquals('voice123', $voiceInfo['file_id']);
        $this->assertEquals(5, $voiceInfo['duration']);
    }

    public function testStickerMessage()
    {
        $webhookData = [
            'update_id' => 123456789,
            'message' => [
                'message_id' => 9,
                'from' => ['id' => 987654321, 'first_name' => 'John'],
                'chat' => ['id' => 987654321],
                'date' => 1234567890,
                'sticker' => [
                    'file_id' => 'sticker123',
                    'file_unique_id' => 'unique123',
                    'width' => 512,
                    'height' => 512,
                    'is_animated' => false,
                    'emoji' => '😀'
                ]
            ]
        ];

        $driver = new TelegramDriver($this->testToken, $webhookData);

        $this->assertEquals('sticker', $driver->getMessageType());
    }

    public function testEmptyWebhookData()
    {
        $driver = new TelegramDriver($this->testToken, []);

        $this->assertNull($driver->getMessage());
        $this->assertNull($driver->getSenderId());
        $this->assertNull($driver->getChatId());
        $this->assertFalse($driver->hasMessage());
        $this->assertNull($driver->getMessageType());
    }

    public function testInvalidWebhookData()
    {
        // Test with malformed webhook data
        $driver = new TelegramDriver($this->testToken, ['invalid' => 'data']);

        $this->assertNull($driver->getMessage());
        $this->assertNull($driver->getSenderId());
        $this->assertFalse($driver->hasMessage());
    }

    /**
     * Note: The following methods require actual API calls and should be tested in integration tests
     * with proper mocking or in a test environment:
     *
     * - sendMessage()
     * - sendPhoto()
     * - sendDocument()
     * - sendKeyboard()
     * - sendInlineKeyboard()
     * - sendLocation()
     * - setWebhook()
     * - getWebhookInfo()
     * - deleteWebhook()
     * - answerCallbackQuery()
     * - getBotInfo()
     */

    public function testSendMessageValidation()
    {
        $driver = new TelegramDriver($this->testToken);

        // Should return false when no chat ID is available
        $result = $driver->sendMessage('Test message');
        $this->assertFalse($result);
    }

    public function testSendPhotoValidation()
    {
        $driver = new TelegramDriver($this->testToken);

        // Should return false when no chat ID is available
        $result = $driver->sendPhoto('https://example.com/photo.jpg');
        $this->assertFalse($result);
    }

    public function testSendLocationValidation()
    {
        $driver = new TelegramDriver($this->testToken);

        // Should return false when no chat ID is available
        $result = $driver->sendLocation(37.7749, -122.4194);
        $this->assertFalse($result);
    }
}
