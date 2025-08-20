<?php

namespace TusharKhan\Chatbot\Tests\Drivers;

use PHPUnit\Framework\TestCase;
use TusharKhan\Chatbot\Drivers\WebDriver;

class WebDriverTest extends TestCase
{
    // Example webhook URL (replace with your own during development)
    protected function setUp(): void
    {
        // Clear any existing data
        $_POST = [];
        $_GET = [];
        $_SESSION = [];
    }

    public function testSendMessage()
    {
        $driver = new WebDriver();
        $result = $driver->sendMessage('Test response', 'user123');

        $this->assertTrue($result);

        $responses = $driver->getResponses();
        $this->assertCount(1, $responses);
        $this->assertEquals('Test response', $responses[0]['message']);
        $this->assertEquals('user123', $responses[0]['sender_id']);
    }

    public function testMultipleResponses()
    {
        $driver = new WebDriver();
        $driver->sendMessage('First response');
        $driver->sendMessage('Second response');

        $responses = $driver->getResponses();
        $this->assertCount(2, $responses);
        $this->assertEquals('First response', $responses[0]['message']);
        $this->assertEquals('Second response', $responses[1]['message']);
    }

    public function testClearResponses()
    {
        $driver = new WebDriver();
        $driver->sendMessage('Test response');

        $this->assertCount(1, $driver->getResponses());

        $driver->clearResponses();
        $this->assertEmpty($driver->getResponses());
    }
}
