<?php

namespace TusharKhan\Chatbot\Contracts;

interface DriverInterface
{
    /**
     * Get the incoming message
     */
    public function getMessage(): ?string;

    /**
     * Get the sender ID
     */
    public function getSenderId(): ?string;

    /**
     * Send a message
     */
    public function sendMessage(string $message, ?string $senderId = null): bool;

    /**
     * Get additional data from the driver
     */
    public function getData(): array;

    /**
     * Check if there's an incoming message
     */
    public function hasMessage(): bool;
}
