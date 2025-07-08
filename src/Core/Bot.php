<?php

namespace TusharKhan\Chatbot\Core;

use TusharKhan\Chatbot\Contracts\DriverInterface;
use TusharKhan\Chatbot\Contracts\StorageInterface;
use TusharKhan\Chatbot\Storage\ArrayStore;

class Bot
{
    private $driver;
    private $storage;
    private $matcher;
    private $handlers = [];
    private $fallbackHandler;
    private $middleware = [];
    private $currentConversation;

    public function __construct(DriverInterface $driver, StorageInterface $storage = null)
    {
        $this->driver = $driver;
        $this->storage = $storage ?: new ArrayStore();
        $this->matcher = new Matcher();
    }

    /**
     * Add a message handler
     */
    public function hears($pattern, callable $handler): self
    {
        $this->handlers[] = [
            'pattern' => $pattern,
            'handler' => $handler
        ];
        return $this;
    }

    /**
     * Set fallback handler
     */
    public function fallback(callable $handler): self
    {
        $this->fallbackHandler = $handler;
        return $this;
    }

    /**
     * Add middleware
     */
    public function middleware(callable $middleware): self
    {
        $this->middleware[] = $middleware;
        return $this;
    }

    /**
     * Listen for incoming messages
     */
    public function listen(): void
    {
        if (!$this->driver->hasMessage()) {
            return;
        }

        $message = $this->driver->getMessage();
        $senderId = $this->driver->getSenderId();

        if (!$message || !$senderId) {
            return;
        }

        // Initialize conversation
        $this->currentConversation = new Conversation($this->storage, $senderId);
        $this->currentConversation->addMessage('user', $message);

        // Create context
        $context = new Context($this->driver, $this->currentConversation, $message, $senderId);

        // Run middleware
        foreach ($this->middleware as $middleware) {
            if ($middleware($context) === false) {
                return; // Stop processing if middleware returns false
            }
        }

        // Find matching handler
        $handled = false;
        foreach ($this->handlers as $handler) {
            if ($this->matcher->match($message, $handler['pattern'])) {
                $params = $this->matcher->extractParams($message, $handler['pattern']);
                $context->setParams($params);
                
                $response = $handler['handler']($context);
                if ($response !== null) {
                    $this->sendResponse($response, $senderId);
                }
                
                $handled = true;
                break;
            }
        }

        // Run fallback if no handler matched
        if (!$handled && $this->fallbackHandler) {
            $response = ($this->fallbackHandler)($context);
            if ($response !== null) {
                $this->sendResponse($response, $senderId);
            }
        }
    }

    /**
     * Send a message
     */
    public function say(string $message, ?string $senderId = null): bool
    {
        if ($this->currentConversation) {
            $this->currentConversation->addMessage('bot', $message);
        }
        
        return $this->driver->sendMessage($message, $senderId);
    }

    /**
     * Get the current conversation
     */
    public function conversation(): ?Conversation
    {
        return $this->currentConversation;
    }

    /**
     * Get the driver
     */
    public function driver(): DriverInterface
    {
        return $this->driver;
    }

    /**
     * Get the storage
     */
    public function storage(): StorageInterface
    {
        return $this->storage;
    }

    /**
     * Send response (handles different response types)
     */
    private function sendResponse($response, string $senderId): void
    {
        if (is_string($response)) {
            $this->say($response, $senderId);
        } elseif (is_array($response)) {
            foreach ($response as $message) {
                if (is_string($message)) {
                    $this->say($message, $senderId);
                }
            }
        }
    }
}

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
}
