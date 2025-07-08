<?php

namespace TusharKhan\Chatbot\Contracts;

interface StorageInterface
{
    /**
     * Store a key-value pair
     */
    public function set(string $key, $value): bool;

    /**
     * Get a value by key
     */
    public function get(string $key, $default = null);

    /**
     * Check if a key exists
     */
    public function has(string $key): bool;

    /**
     * Delete a key
     */
    public function delete(string $key): bool;

    /**
     * Clear all data
     */
    public function clear(): bool;

    /**
     * Get conversation data for a user
     */
    public function getConversation(string $userId): array;

    /**
     * Set conversation data for a user
     */
    public function setConversation(string $userId, array $data): bool;

    /**
     * Clear conversation data for a user
     */
    public function clearConversation(string $userId): bool;
}
