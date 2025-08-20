<?php

namespace TusharKhan\Chatbot\Core;

use TusharKhan\Chatbot\Contracts\DriverInterface;

/**
 * Context class for handlers
 */
class Context
{
    private $driver;
    private $conversation;
    private $message;
    private $senderId;
    private $params = [];

    public function __construct(DriverInterface $driver, Conversation $conversation, string $message, string $senderId)
    {
        $this->driver = $driver;
        $this->conversation = $conversation;
        $this->message = $message;
        $this->senderId = $senderId;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getSenderId(): string
    {
        return $this->senderId;
    }

    public function getConversation(): Conversation
    {
        return $this->conversation;
    }

    public function getDriver(): DriverInterface
    {
        return $this->driver;
    }

    public function setParams(array $params): void
    {
        $this->params = $params;
    }

    public function getParams(): array
    {
        return $this->params;
    }

    public function getParam(string $key, $default = null)
    {
        return $this->params[$key] ?? $default;
    }

    public function getData(): array
    {
        return $this->driver->getData();
    }
}
