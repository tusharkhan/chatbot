<?php

namespace TusharKhan\Chatbot\Storage;

use TusharKhan\Chatbot\Contracts\StorageInterface;

class ArrayStore implements StorageInterface
{
    private $data = [];
    private $conversations = [];

    public function set(string $key, $value): bool
    {
        $this->data[$key] = $value;
        return true;
    }

    public function get(string $key, $default = null)
    {
        return $this->data[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        return isset($this->data[$key]);
    }

    public function delete(string $key): bool
    {
        unset($this->data[$key]);
        return true;
    }

    public function clear(): bool
    {
        $this->data = [];
        return true;
    }

    public function getConversation(string $userId): array
    {
        return $this->conversations[$userId] ?? [];
    }

    public function setConversation(string $userId, array $data): bool
    {
        $this->conversations[$userId] = $data;
        return true;
    }

    public function clearConversation(string $userId): bool
    {
        unset($this->conversations[$userId]);
        return true;
    }

    /**
     * Get all data (useful for debugging)
     */
    public function getAllData(): array
    {
        return $this->data;
    }

    /**
     * Get all conversations (useful for debugging)
     */
    public function getAllConversations(): array
    {
        return $this->conversations;
    }
}
