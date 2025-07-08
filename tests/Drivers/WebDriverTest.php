<?php

namespace TusharKhan\Chatbot\Tests\Drivers;

use PHPUnit\Framework\TestCase;
use TusharKhan\Chatbot\Drivers\WebDriver;

class WebDriverTest extends TestCase
{
    protected function setUp(): void
    {
        // Clear any existing data
        $_POST = [];
        $_GET = [];
        $_SESSION = [];
    }

    public function testGetMessageFromPost()
    {
        $_POST['message'] = 'Hello from POST';
        $_POST['sender_id'] = 'user123';
        
        $driver = new WebDriver();
        
        $this->assertEquals('Hello from POST', $driver->getMessage());
        $this->assertEquals('user123', $driver->getSenderId());
        $this->assertTrue($driver->hasMessage());
    }

    public function testGetMessageFromGet()
    {
        $_GET['message'] = 'Hello from GET';
        $_GET['user_id'] = 'user456';
        
        $driver = new WebDriver();
        
        $this->assertEquals('Hello from GET', $driver->getMessage());
        $this->assertEquals('user456', $driver->getSenderId());
        $this->assertTrue($driver->hasMessage());
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

    public function testGetData()
    {
        $_POST['message'] = 'Hello';
        $_POST['extra_data'] = 'some value';
        $_GET['param'] = 'test';
        
        $driver = new WebDriver();
        $data = $driver->getData();
        
        $this->assertEquals('Hello', $data['message']);
        $this->assertEquals('some value', $data['extra_data']);
        $this->assertEquals('test', $data['param']);
    }
}
