<?php

namespace TusharKhan\Chatbot\Core;

use TusharKhan\Chatbot\Contracts\StorageInterface;

class Conversation
{
    private $storage;
    private $userId;
    private $data;

    public function __construct(StorageInterface $storage, string $userId)
    {
        $this->storage = $storage;
        $this->userId = $userId;
        $this->data = $this->storage->getConversation($userId);
    }

    /**
     * Set conversation state
     */
    public function setState(string $state): self
    {
        $this->data['state'] = $state;
        $this->save();
        return $this;
    }

    /**
     * Get current state
     */
    public function getState(): ?string
    {
        return $this->data['state'] ?? null;
    }

    /**
     * Set a conversation variable
     */
    public function set(string $key, $value): self
    {
        $this->data['variables'][$key] = $value;
        $this->save();
        return $this;
    }

    /**
     * Get a conversation variable
     */
    public function get(string $key, $default = null)
    {
        return $this->data['variables'][$key] ?? $default;
    }

    /**
     * Check if a variable exists
     */
    public function has(string $key): bool
    {
        return isset($this->data['variables'][$key]);
    }

    /**
     * Remove a variable
     */
    public function remove(string $key): self
    {
        unset($this->data['variables'][$key]);
        $this->save();
        return $this;
    }

    /**
     * Clear all conversation data
     */
    public function clear(): self
    {
        $this->data = [];
        $this->storage->clearConversation($this->userId);
        return $this;
    }

    /**
     * Add message to history
     */
    public function addMessage(string $type, string $message): self
    {
        if (!isset($this->data['history'])) {
            $this->data['history'] = [];
        }

        $this->data['history'][] = [
            'type' => $type,
            'message' => $message,
            'timestamp' => time()
        ];

        // Keep only last 50 messages
        if (count($this->data['history']) > 50) {
            $this->data['history'] = array_slice($this->data['history'], -50);
        }

        $this->save();
        return $this;
    }

    /**
     * Get message history
     */
    public function getHistory(): array
    {
        return $this->data['history'] ?? [];
    }

    /**
     * Get last message
     */
    public function getLastMessage(): ?array
    {
        $history = $this->getHistory();
        return empty($history) ? null : end($history);
    }

    /**
     * Check if user is in a specific state
     */
    public function isInState(string $state): bool
    {
        return $this->getState() === $state;
    }

    /**
     * Get all conversation data
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Save conversation data
     */
    private function save(): void
    {
        $this->storage->setConversation($this->userId, $this->data);
    }
}
