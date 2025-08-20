<?php

namespace TusharKhan\Chatbot\Tests\Core;

use PHPUnit\Framework\TestCase;
use TusharKhan\Chatbot\Core\Conversation;
use TusharKhan\Chatbot\Storage\ArrayStore;

class ConversationTest extends TestCase
{
    private $storage;
    private $conversation;

    protected function setUp(): void
    {
        $this->storage = new ArrayStore();
        $this->conversation = new Conversation($this->storage, 'user123');
    }

    public function testSetAndGetState()
    {
        $this->conversation->setState('waiting_for_name');
        $this->assertEquals('waiting_for_name', $this->conversation->getState());
    }

    public function testSetAndGetVariable()
    {
        $this->conversation->set('name', 'John');
        $this->assertEquals('John', $this->conversation->get('name'));
    }

    public function testHasVariable()
    {
        $this->conversation->set('age', 25);
        $this->assertTrue($this->conversation->has('age'));
        $this->assertFalse($this->conversation->has('height'));
    }

    public function testRemoveVariable()
    {
        $this->conversation->set('temp', 'value');
        $this->assertTrue($this->conversation->has('temp'));

        $this->conversation->remove('temp');
        $this->assertFalse($this->conversation->has('temp'));
    }

    public function testAddMessage()
    {
        $this->conversation->addMessage('user', 'Hello');
        $this->conversation->addMessage('bot', 'Hi there!');

        $history = $this->conversation->getHistory();
        $this->assertCount(2, $history);
        $this->assertEquals('Hello', $history[0]['message']);
        $this->assertEquals('user', $history[0]['type']);
        $this->assertEquals('Hi there!', $history[1]['message']);
        $this->assertEquals('bot', $history[1]['type']);
    }

    public function testGetLastMessage()
    {
        $this->conversation->addMessage('user', 'First message');
        $this->conversation->addMessage('bot', 'Last message');

        $lastMessage = $this->conversation->getLastMessage();
        $this->assertEquals('Last message', $lastMessage['message']);
        $this->assertEquals('bot', $lastMessage['type']);
    }

    public function testIsInState()
    {
        $this->conversation->setState('ordering');
        $this->assertTrue($this->conversation->isInState('ordering'));
        $this->assertFalse($this->conversation->isInState('paying'));
    }

    public function testClear()
    {
        $this->conversation->setState('test');
        $this->conversation->set('name', 'John');
        $this->conversation->addMessage('user', 'Hello');

        $this->conversation->clear();

        $this->assertNull($this->conversation->getState());
        $this->assertNull($this->conversation->get('name'));
        $this->assertEmpty($this->conversation->getHistory());
    }

    public function testMessageHistoryLimit()
    {
        // Add more than 50 messages
        for ($i = 1; $i <= 55; $i++) {
            $this->conversation->addMessage('user', "Message $i");
        }

        $history = $this->conversation->getHistory();
        $this->assertCount(50, $history); // Should be limited to 50
        $this->assertEquals('Message 6', $history[0]['message']); // First 5 should be removed
        $this->assertEquals('Message 55', $history[49]['message']); // Last should be preserved
    }
}
