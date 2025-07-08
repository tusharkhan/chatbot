<?php

namespace TusharKhan\Chatbot\Tests\Storage;

use PHPUnit\Framework\TestCase;
use TusharKhan\Chatbot\Storage\ArrayStore;

class ArrayStoreTest extends TestCase
{
    private $store;

    protected function setUp(): void
    {
        $this->store = new ArrayStore();
    }

    public function testSetAndGet()
    {
        $this->store->set('key1', 'value1');
        $this->assertEquals('value1', $this->store->get('key1'));
    }

    public function testGetWithDefault()
    {
        $this->assertEquals('default', $this->store->get('nonexistent', 'default'));
    }

    public function testHas()
    {
        $this->store->set('exists', 'value');
        $this->assertTrue($this->store->has('exists'));
        $this->assertFalse($this->store->has('not_exists'));
    }

    public function testDelete()
    {
        $this->store->set('temp', 'value');
        $this->assertTrue($this->store->has('temp'));
        
        $this->store->delete('temp');
        $this->assertFalse($this->store->has('temp'));
    }

    public function testClear()
    {
        $this->store->set('key1', 'value1');
        $this->store->set('key2', 'value2');
        
        $this->store->clear();
        
        $this->assertFalse($this->store->has('key1'));
        $this->assertFalse($this->store->has('key2'));
    }

    public function testConversationStorage()
    {
        $conversationData = [
            'state' => 'ordering',
            'variables' => ['name' => 'John']
        ];
        
        $this->store->setConversation('user123', $conversationData);
        $retrieved = $this->store->getConversation('user123');
        
        $this->assertEquals($conversationData, $retrieved);
    }

    public function testClearConversation()
    {
        $this->store->setConversation('user123', ['state' => 'test']);
        $this->assertNotEmpty($this->store->getConversation('user123'));
        
        $this->store->clearConversation('user123');
        $this->assertEmpty($this->store->getConversation('user123'));
    }
}
