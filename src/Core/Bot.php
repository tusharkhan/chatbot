<?php

namespace TusharKhan\Chatbot\Core;

use TusharKhan\Chatbot\Contracts\DriverInterface;
use TusharKhan\Chatbot\Contracts\StorageInterface;
use TusharKhan\Chatbot\Storage\ArrayStore;
use Illuminate\Support\Facades\Log;

class Bot
{
    private $driver;
    private $storage;
    private $matcher;
    private $handlers = [];
    private $commandHandlers = [];
    private $fallbackHandler;
    private $middleware = [];
    private $currentConversation;

    public function __construct(DriverInterface $driver, ?StorageInterface $storage = null)
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

    public function on(string $event, callable $handler): self
    {
        $this->commandHandlers[] = [
            'command' => $event,
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
     * Load conversations from JSON file
     */
    public function loadConversations(string $jsonFilePath): self
    {
        if (!file_exists($jsonFilePath)) {
            throw new \InvalidArgumentException("JSON file not found: {$jsonFilePath}");
        }

        $jsonContent = file_get_contents($jsonFilePath);
        $conversations = json_decode($jsonContent, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException("Invalid JSON in file: {$jsonFilePath}");
        }

        if (!isset($conversations['conversations']) || !is_array($conversations['conversations'])) {
            throw new \InvalidArgumentException("JSON must contain 'conversations' array");
        }

        foreach ($conversations['conversations'] as $conversation) {
            $this->loadConversationFromArray($conversation);
        }

        return $this;
    }

    /**
     * Load a single conversation from array
     */
    private function loadConversationFromArray(array $conversation): void
    {
        if (!isset($conversation['pattern']) || !isset($conversation['response'])) {
            return; // Skip invalid conversations
        }

        $pattern = $conversation['pattern'];
        $response = $conversation['response'];
        $conditions = $conversation['conditions'] ?? [];

        $this->hears($pattern, function($context) use ($response, $conditions) {
            // Check conditions if any
            if (!empty($conditions)) {
                foreach ($conditions as $condition) {
                    if (!$this->evaluateCondition($context, $condition)) {
                        return null; // Don't respond if condition fails
                    }
                }
            }

            // Handle different response types
            if (is_string($response)) {
                return $this->processResponseString($response, $context);
            } elseif (is_array($response)) {
                return $this->processResponseArray($response, $context);
            }

            return $response;
        });
    }

    /**
     * Process response string (handle placeholders)
     */
    private function processResponseString(string $response, $context): string
    {
        // Replace parameter placeholders
        $params = $context->getParams();
        foreach ($params as $key => $value) {
            $response = str_replace("{{$key}}", $value, $response);
        }

        // Replace conversation data placeholders
        $conversation = $context->getConversation();
        preg_match_all('/\{conversation\.([^}]+)\}/', $response, $matches);
        foreach ($matches[1] as $key) {
            $value = $conversation->get($key, '');
            $response = str_replace("{conversation.{$key}}", $value, $response);
        }

        return $response;
    }

    /**
     * Process response array (handle multiple responses or actions)
     */
    private function processResponseArray(array $response, $context): ?string
    {
        if (isset($response['text'])) {
            $text = $this->processResponseString($response['text'], $context);

            // Handle actions
            if (isset($response['actions'])) {
                foreach ($response['actions'] as $action) {
                    $this->executeAction($action, $context);
                }
            }

            return $text;
        }

        // Handle random responses
        if (isset($response['random'])) {
            $randomResponse = $response['random'][array_rand($response['random'])];
            return $this->processResponseString($randomResponse, $context);
        }

        return null;
    }

    /**
     * Evaluate a condition
     */
    private function evaluateCondition($context, array $condition): bool
    {
        $type = $condition['type'] ?? '';
        $key = $condition['key'] ?? '';
        $value = $condition['value'] ?? '';
        $operator = $condition['operator'] ?? '=';

        switch ($type) {
            case 'conversation':
                $conversationValue = $context->getConversation()->get($key);
                return $this->compareValues($conversationValue, $value, $operator);

            case 'param':
                $paramValue = $context->getParam($key);
                return $this->compareValues($paramValue, $value, $operator);

            default:
                return true;
        }
    }

    /**
     * Compare values with operator
     */
    private function compareValues($left, $right, string $operator): bool
    {
        switch ($operator) {
            case '=':
            case '==':
                return $left == $right;
            case '!=':
                return $left != $right;
            case '>':
                return $left > $right;
            case '<':
                return $left < $right;
            case 'contains':
                return strpos($left, $right) !== false;
            case 'exists':
                return !empty($left);
            default:
                return true;
        }
    }

    /**
     * Execute an action
     */
    private function executeAction(array $action, $context): void
    {
        $type = $action['type'] ?? '';
        $key = $action['key'] ?? '';
        $value = $action['value'] ?? '';

        switch ($type) {
            case 'set':
                // Process the value to replace placeholders
                $processedValue = $this->processActionValue($value, $context);
                $context->getConversation()->set($key, $processedValue);
                break;
            case 'increment':
                $current = $context->getConversation()->get($key, 0);
                $context->getConversation()->set($key, $current + 1);
                break;
        }
    }

    /**
     * Process action value to replace placeholders
     */
    private function processActionValue(string $value, $context): string
    {
        // Replace parameter placeholders
        $params = $context->getParams();
        foreach ($params as $key => $paramValue) {
            $value = str_replace("{{$key}}", $paramValue, $value);
        }

        // Replace conversation data placeholders
        $conversation = $context->getConversation();
        preg_match_all('/\{conversation\.([^}]+)\}/', $value, $matches);
        foreach ($matches[1] as $key) {
            $conversationValue = $conversation->get($key, '');
            $value = str_replace("{conversation.{$key}}", $conversationValue, $value);
        }

        return $value;
    }

    /**
     * Check if message is a command and extract command data
     */
    private function parseCommand(string $message): ?array
    {
        // Check for slash commands (like Slack commands)
        if (preg_match('/^\/([a-zA-Z0-9_-]+)(?:\s+(.*))?$/', trim($message), $matches)) {
            return [
                'command' => $matches[1],
                'arguments' => isset($matches[2]) ? trim($matches[2]) : '',
                'args' => isset($matches[2]) ? array_filter(explode(' ', trim($matches[2]))) : []
            ];
        }

        return null;
    }

    /**
     * Find matching command handler
     */
    private function findCommandHandler(string $command): ?array
    {
        foreach ($this->commandHandlers as $handler) {
            if ($handler['command'] === $command) {
                return $handler;
            }
        }

        return null;
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

        // Check for commands first
        $handled = false;
        $commandData = $this->parseCommand($message);

        if ($commandData) {
            $commandHandler = $this->findCommandHandler($commandData['command']);

            if ($commandHandler) {
                // Set command parameters in context
                $context->setParams([
                    'command' => $commandData['command'],
                    'arguments' => $commandData['arguments'],
                    'args' => $commandData['args']
                ]);

                $response = $commandHandler['handler']($context);
                if ($response !== null) {
                    $this->sendResponse($response, $senderId);
                }

                $handled = true;
            }
        }

        // Find matching pattern handler if no command was handled
        if (!$handled) {
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
