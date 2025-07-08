<?php

namespace TusharKhan\Chatbot\Tests\Core;

use PHPUnit\Framework\TestCase;
use TusharKhan\Chatbot\Core\Bot;
use TusharKhan\Chatbot\Tests\Mocks\MockDriver;
use TusharKhan\Chatbot\Storage\ArrayStore;

class BotTest extends TestCase
{
    private $bot;
    private $driver;
    private $storage;

    protected function setUp(): void
    {
        $this->driver = new MockDriver();
        $this->storage = new ArrayStore();
        $this->bot = new Bot($this->driver, $this->storage);
    }

    public function testSimpleMessageHandling()
    {
        $this->driver->setIncomingMessage('hello', 'user123');
        
        $this->bot->hears('hello', function($context) {
            return 'Hi there!';
        });

        $this->bot->listen();

        $responses = $this->driver->getResponses();
        $this->assertCount(1, $responses);
        $this->assertEquals('Hi there!', $responses[0]['message']);
    }

    public function testParameterExtraction()
    {
        $this->driver->setIncomingMessage('my name is John', 'user123');
        
        $this->bot->hears('my name is {name}', function($context) {
            $name = $context->getParam('name');
            return "Nice to meet you, $name!";
        });

        $this->bot->listen();

        $responses = $this->driver->getResponses();
        $this->assertCount(1, $responses);
        $this->assertEquals('Nice to meet you, John!', $responses[0]['message']);
    }

    public function testFallbackHandler()
    {
        $this->driver->setIncomingMessage('unknown command', 'user123');
        
        $this->bot->hears('hello', function($context) {
            return 'Hi!';
        });

        $this->bot->fallback(function($context) {
            return "I don't understand that.";
        });

        $this->bot->listen();

        $responses = $this->driver->getResponses();
        $this->assertEquals("I don't understand that.", $responses[0]['message']);
    }

    public function testMultiplePatterns()
    {
        $this->driver->setIncomingMessage('hi', 'user123');
        
        $this->bot->hears(['hello', 'hi', 'hey'], function($context) {
            return 'Hello!';
        });

        $this->bot->listen();

        $responses = $this->driver->getResponses();
        $this->assertEquals('Hello!', $responses[0]['message']);
    }

    public function testConversationState()
    {
        $this->driver->setIncomingMessage('start order', 'user123');
        
        $this->bot->hears('start order', function($context) {
            $context->getConversation()->setState('ordering');
            return 'What would you like to order?';
        });

        $this->bot->listen();

        // Check if conversation state was set
        $conversation = $this->bot->conversation();
        $this->assertEquals('ordering', $conversation->getState());
    }

    public function testMiddleware()
    {
        $this->driver->setIncomingMessage('hello', 'user123');
        
        $middlewareExecuted = false;
        $this->bot->middleware(function($context) use (&$middlewareExecuted) {
            $middlewareExecuted = true;
            return true; // Continue processing
        });

        $this->bot->hears('hello', function($context) {
            return 'Hi!';
        });

        $this->bot->listen();

        $this->assertTrue($middlewareExecuted);
    }

    public function testMiddlewareCanStopProcessing()
    {
        $this->driver->setIncomingMessage('blocked', 'user123');
        
        $this->bot->middleware(function($context) {
            if ($context->getMessage() === 'blocked') {
                return false; // Stop processing
            }
            return true;
        });

        $handlerExecuted = false;
        $this->bot->hears('blocked', function($context) use (&$handlerExecuted) {
            $handlerExecuted = true;
            return 'This should not execute';
        });

        $this->bot->listen();

        $this->assertFalse($handlerExecuted);
        $responses = $this->driver->getResponses();
        $this->assertEmpty($responses);
    }

    public function testArrayResponse()
    {
        $this->driver->setIncomingMessage('info', 'user123');
        
        $this->bot->hears('info', function($context) {
            return [
                'Here is some information:',
                'Line 1',
                'Line 2'
            ];
        });

        $this->bot->listen();

        $responses = $this->driver->getResponses();
        $this->assertCount(3, $responses);
        $this->assertEquals('Here is some information:', $responses[0]['message']);
        $this->assertEquals('Line 1', $responses[1]['message']);
        $this->assertEquals('Line 2', $responses[2]['message']);
    }
}
