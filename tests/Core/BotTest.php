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

    public function testLoadConversationsFromValidJson()
    {
        $jsonPath = $this->createTempJsonFile([
            'conversations' => [
                [
                    'pattern' => 'hello',
                    'response' => 'Hi there!'
                ],
                [
                    'pattern' => 'my name is {name}',
                    'response' => 'Nice to meet you, {name}!'
                ]
            ]
        ]);

        $this->bot->loadConversations($jsonPath);

        // Test first conversation
        $this->driver->setIncomingMessage('hello', 'user123');
        $this->bot->listen();

        $responses = $this->driver->getResponses();
        $this->assertEquals('Hi there!', $responses[0]['message']);

        // Reset and test second conversation
        $this->driver->clearResponses();
        $this->driver->setIncomingMessage('my name is John', 'user123');
        $this->bot->listen();

        $responses = $this->driver->getResponses();
        $this->assertEquals('Nice to meet you, John!', $responses[0]['message']);

        unlink($jsonPath);
    }

    public function testLoadConversationsWithRandomResponses()
    {
        $jsonPath = $this->createTempJsonFile([
            'conversations' => [
                [
                    'pattern' => 'hello',
                    'response' => [
                        'random' => ['Hi!', 'Hello!', 'Hey there!']
                    ]
                ]
            ]
        ]);

        $this->bot->loadConversations($jsonPath);

        $this->driver->setIncomingMessage('hello', 'user123');
        $this->bot->listen();

        $responses = $this->driver->getResponses();
        $this->assertContains($responses[0]['message'], ['Hi!', 'Hello!', 'Hey there!']);

        unlink($jsonPath);
    }

    public function testLoadConversationsWithActions()
    {
        $jsonPath = $this->createTempJsonFile([
            'conversations' => [
                [
                    'pattern' => 'my name is {name}',
                    'response' => [
                        'text' => 'Nice to meet you, {name}!',
                        'actions' => [
                            [
                                'type' => 'set',
                                'key' => 'name',
                                'value' => '{name}'
                            ]
                        ]
                    ]
                ],
                [
                    'pattern' => 'what is my name',
                    'response' => 'Your name is {conversation.name}'
                ]
            ]
        ]);

        $this->bot->loadConversations($jsonPath);

        // Set name
        $this->driver->setIncomingMessage('my name is Alice', 'user123');
        $this->bot->listen();

        $responses = $this->driver->getResponses();
        $this->assertEquals('Nice to meet you, Alice!', $responses[0]['message']);

        // Check if name was stored
        $this->driver->clearResponses();
        $this->driver->setIncomingMessage('what is my name', 'user123');
        $this->bot->listen();

        $responses = $this->driver->getResponses();
        $this->assertEquals('Your name is Alice', $responses[0]['message']);

        unlink($jsonPath);
    }

    public function testLoadConversationsWithConditions()
    {
        $jsonPath = $this->createTempJsonFile([
            'conversations' => [
                [
                    'pattern' => 'set status {status}',
                    'response' => [
                        'text' => 'Status set to {status}',
                        'actions' => [
                            [
                                'type' => 'set',
                                'key' => 'status',
                                'value' => '{status}'
                            ]
                        ]
                    ]
                ],
                [
                    'pattern' => 'check status',
                    'response' => 'Your status is {conversation.status}',
                    'conditions' => [
                        [
                            'type' => 'conversation',
                            'key' => 'status',
                            'operator' => 'exists'
                        ]
                    ]
                ],
                [
                    'pattern' => 'check status',
                    'response' => 'No status set yet'
                ]
            ]
        ]);

        $this->bot->loadConversations($jsonPath);

        // Test without status set
        $this->driver->setIncomingMessage('check status', 'user123');
        $this->bot->listen();

        $responses = $this->driver->getResponses();
        $this->assertEquals('No status set yet', $responses[0]['message']);

        // Set status
        $this->driver->clearResponses();
        $this->driver->setIncomingMessage('set status active', 'user123');
        $this->bot->listen();

        $responses = $this->driver->getResponses();
        $this->assertEquals('Status set to active', $responses[0]['message']);

        // Check status again
        $this->driver->clearResponses();
        $this->driver->setIncomingMessage('check status', 'user123');
        $this->bot->listen();

        $responses = $this->driver->getResponses();
        $this->assertEquals('Your status is active', $responses[0]['message']);

        unlink($jsonPath);
    }

    public function testLoadConversationsWithIncrementAction()
    {
        $jsonPath = $this->createTempJsonFile([
            'conversations' => [
                [
                    'pattern' => 'increment',
                    'response' => [
                        'text' => 'Counter incremented',
                        'actions' => [
                            [
                                'type' => 'increment',
                                'key' => 'counter'
                            ]
                        ]
                    ]
                ],
                [
                    'pattern' => 'get counter',
                    'response' => 'Counter is {conversation.counter}'
                ]
            ]
        ]);

        $this->bot->loadConversations($jsonPath);

        // Increment counter multiple times
        for ($i = 0; $i < 3; $i++) {
            $this->driver->clearResponses();
            $this->driver->setIncomingMessage('increment', 'user123');
            $this->bot->listen();
        }

        // Check counter value
        $this->driver->clearResponses();
        $this->driver->setIncomingMessage('get counter', 'user123');
        $this->bot->listen();

        $responses = $this->driver->getResponses();
        $this->assertEquals('Counter is 3', $responses[0]['message']);

        unlink($jsonPath);
    }

    public function testLoadConversationsThrowsExceptionForMissingFile()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('JSON file not found');

        $this->bot->loadConversations('/nonexistent/file.json');
    }

    public function testLoadConversationsThrowsExceptionForInvalidJson()
    {
        $jsonPath = tempnam(sys_get_temp_dir(), 'invalid_json');
        file_put_contents($jsonPath, '{"invalid": json}');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid JSON in file');

        $this->bot->loadConversations($jsonPath);

        unlink($jsonPath);
    }

    public function testLoadConversationsThrowsExceptionForMissingConversationsArray()
    {
        $jsonPath = $this->createTempJsonFile([
            'invalid_structure' => []
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('JSON must contain \'conversations\' array');

        $this->bot->loadConversations($jsonPath);

        unlink($jsonPath);
    }

    public function testConditionOperators()
    {
        $jsonPath = $this->createTempJsonFile([
            'conversations' => [
                [
                    'pattern' => 'set age {age}',
                    'response' => [
                        'text' => 'Age set to {age}',
                        'actions' => [
                            [
                                'type' => 'set',
                                'key' => 'age',
                                'value' => '{age}'
                            ]
                        ]
                    ]
                ],
                [
                    'pattern' => 'check adult',
                    'response' => 'You are an adult',
                    'conditions' => [
                        [
                            'type' => 'conversation',
                            'key' => 'age',
                            'operator' => '>',
                            'value' => '17'
                        ]
                    ]
                ],
                [
                    'pattern' => 'check adult',
                    'response' => 'You are a minor'
                ]
            ]
        ]);

        $this->bot->loadConversations($jsonPath);

        // Test adult case
        $this->driver->setIncomingMessage('set age 25', 'user123');
        $this->bot->listen();

        $this->driver->clearResponses();
        $this->driver->setIncomingMessage('check adult', 'user123');
        $this->bot->listen();

        $responses = $this->driver->getResponses();
        $this->assertEquals('You are an adult', $responses[0]['message']);

        // Test minor case
        $this->driver->clearResponses();
        $this->driver->setIncomingMessage('set age 16', 'user123');
        $this->bot->listen();

        $this->driver->clearResponses();
        $this->driver->setIncomingMessage('check adult', 'user123');
        $this->bot->listen();

        $responses = $this->driver->getResponses();
        $this->assertEquals('You are a minor', $responses[0]['message']);

        unlink($jsonPath);
    }

    /**
     * Helper method to create temporary JSON files for testing
     */
    private function createTempJsonFile(array $data): string
    {
        $jsonPath = tempnam(sys_get_temp_dir(), 'test_conversations');
        file_put_contents($jsonPath, json_encode($data, JSON_PRETTY_PRINT));
        return $jsonPath;
    }
}
