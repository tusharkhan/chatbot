<?php

namespace TusharKhan\Chatbot\Tests\Mocks;

use TusharKhan\Chatbot\Contracts\DriverInterface;

class MockDriver implements DriverInterface
{
    private $message;
    private $senderId;
    private $data = [];
    private $responses = [];

    public function setIncomingMessage(string $message, string $senderId, array $data = []): void
    {
        $this->message = $message;
        $this->senderId = $senderId;
        $this->data = $data;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function getSenderId(): ?string
    {
        return $this->senderId;
    }

    public function sendMessage(string $message, ?string $senderId = null): bool
    {
        $this->responses[] = [
            'message' => $message,
            'sender_id' => $senderId ?: $this->senderId,
            'timestamp' => time()
        ];
        return true;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function hasMessage(): bool
    {
        return !empty($this->message);
    }

    public function getResponses(): array
    {
        return $this->responses;
    }

    public function clearResponses(): void
    {
        $this->responses = [];
    }

    public function getLastResponse(): ?array
    {
        return empty($this->responses) ? null : end($this->responses);
    }
}
