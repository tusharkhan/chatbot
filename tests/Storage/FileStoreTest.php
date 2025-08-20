<?php

namespace TusharKhan\Chatbot\Tests\Storage;

use PHPUnit\Framework\TestCase;
use TusharKhan\Chatbot\Storage\FileStore;

class FileStoreTest extends TestCase
{
    private $store;
    private $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'chatbot_test_' . uniqid();
        $this->store = new FileStore($this->tempDir);
    }

    protected function tearDown(): void
    {
        // Clean up test files
        if (is_dir($this->tempDir)) {
            $files = glob($this->tempDir . DIRECTORY_SEPARATOR . '*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir($this->tempDir);
        }
    }

    public function testSetAndGet()
    {
        $this->store->set('test_key', 'test_value');
        $this->assertEquals('test_value', $this->store->get('test_key'));
    }

    public function testPersistence()
    {
        $this->store->set('persistent_key', 'persistent_value');

        // Create a new store instance with the same path
        $newStore = new FileStore($this->tempDir);
        $this->assertEquals('persistent_value', $newStore->get('persistent_key'));
    }

    public function testConversationPersistence()
    {
        $conversationData = [
            'state' => 'ordering',
            'variables' => ['name' => 'John', 'age' => 25]
        ];

        $this->store->setConversation('user123', $conversationData);

        // Create a new store instance
        $newStore = new FileStore($this->tempDir);
        $retrieved = $newStore->getConversation('user123');

        $this->assertEquals($conversationData, $retrieved);
    }

    public function testCleanupOldConversations()
    {
        // Create a conversation with old timestamp
        $oldConversation = [
            'state' => 'old',
            'history' => [
                [
                    'type' => 'user',
                    'message' => 'old message',
                    'timestamp' => time() - (40 * 24 * 60 * 60) // 40 days ago
                ]
            ]
        ];

        $newConversation = [
            'state' => 'new',
            'history' => [
                [
                    'type' => 'user',
                    'message' => 'new message',
                    'timestamp' => time() - (10 * 24 * 60 * 60) // 10 days ago
                ]
            ]
        ];

        $this->store->setConversation('old_user', $oldConversation);
        $this->store->setConversation('new_user', $newConversation);

        // Cleanup conversations older than 30 days
        $cleaned = $this->store->cleanupOldConversations(30);

        $this->assertEquals(1, $cleaned);
        $this->assertEmpty($this->store->getConversation('old_user'));
        $this->assertNotEmpty($this->store->getConversation('new_user'));
    }

    public function testGetPath()
    {
        $this->assertEquals($this->tempDir, $this->store->getPath());
    }
}
