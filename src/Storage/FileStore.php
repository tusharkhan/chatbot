<?php

namespace TusharKhan\Chatbot\Storage;

use TusharKhan\Chatbot\Contracts\StorageInterface;

class FileStore implements StorageInterface
{
    private $basePath;
    private $dataFile;
    private $conversationsFile;
    private $data = [];
    private $conversations = [];
    private $loaded = false;

    public function __construct(?string $basePath = null)
    {
        $this->basePath = $basePath ?: sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'chatbot';

        if (!is_dir($this->basePath)) {
            mkdir($this->basePath, 0755, true);
        }

        $this->dataFile = $this->basePath . DIRECTORY_SEPARATOR . 'data.json';
        $this->conversationsFile = $this->basePath . DIRECTORY_SEPARATOR . 'conversations.json';

        $this->load();
    }

    public function set(string $key, $value): bool
    {
        $this->ensureLoaded();
        $this->data[$key] = $value;
        return $this->saveData();
    }

    public function get(string $key, $default = null)
    {
        $this->ensureLoaded();
        return $this->data[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        $this->ensureLoaded();
        return isset($this->data[$key]);
    }

    public function delete(string $key): bool
    {
        $this->ensureLoaded();
        unset($this->data[$key]);
        return $this->saveData();
    }

    public function clear(): bool
    {
        $this->data = [];
        return $this->saveData();
    }

    public function getConversation(string $userId): array
    {
        $this->ensureLoaded();
        return $this->conversations[$userId] ?? [];
    }

    public function setConversation(string $userId, array $data): bool
    {
        $this->ensureLoaded();
        $this->conversations[$userId] = $data;
        return $this->saveConversations();
    }

    public function clearConversation(string $userId): bool
    {
        $this->ensureLoaded();
        unset($this->conversations[$userId]);
        return $this->saveConversations();
    }

    /**
     * Load data from files
     */
    private function load(): void
    {
        if ($this->loaded) {
            return;
        }

        // Load general data
        if (file_exists($this->dataFile)) {
            $content = file_get_contents($this->dataFile);
            $this->data = json_decode($content, true) ?: [];
        } else {
            // create empty data file if it doesn't exist
            file_put_contents($this->dataFile, json_encode([]), LOCK_EX);
            $this->data = [];
        }

        // Load conversations
        if (file_exists($this->conversationsFile)) {
            $content = file_get_contents($this->conversationsFile);
            $this->conversations = json_decode($content, true) ?: [];
        } else {
            // create empty conversations file if it doesn't exist
            file_put_contents($this->conversationsFile, json_encode([]), LOCK_EX);
            $this->conversations = [];
        }

        $this->loaded = true;
    }

    /**
     * Ensure data is loaded
     */
    private function ensureLoaded(): void
    {
        if (!$this->loaded) {
            $this->load();
        }
    }

    /**
     * Save general data
     */
    private function saveData(): bool
    {
        $content = json_encode($this->data, JSON_PRETTY_PRINT);
        return file_put_contents($this->dataFile, $content, LOCK_EX) !== false;
    }

    /**
     * Save conversations
     */
    private function saveConversations(): bool
    {
        $content = json_encode($this->conversations, JSON_PRETTY_PRINT);
        return file_put_contents($this->conversationsFile, $content, LOCK_EX) !== false;
    }

    /**
     * Get storage path
     */
    public function getPath(): string
    {
        return $this->basePath;
    }

    /**
     * Clean up old conversations (older than specified days)
     */
    public function cleanupOldConversations(int $days = 30): int
    {
        $this->ensureLoaded();
        $cutoff = time() - ($days * 24 * 60 * 60);
        $cleaned = 0;

        foreach ($this->conversations as $userId => $conversation) {
            $lastActivity = 0;

            if (isset($conversation['history'])) {
                foreach ($conversation['history'] as $message) {
                    if (isset($message['timestamp']) && $message['timestamp'] > $lastActivity) {
                        $lastActivity = $message['timestamp'];
                    }
                }
            }

            if ($lastActivity > 0 && $lastActivity < $cutoff) {
                unset($this->conversations[$userId]);
                $cleaned++;
            }
        }

        if ($cleaned > 0) {
            $this->saveConversations();
        }

        return $cleaned;
    }
}
