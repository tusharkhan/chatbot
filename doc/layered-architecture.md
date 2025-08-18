# Layered Architecture

This document describes the layered architecture of the Chatbot package, explaining how different components interact and the separation of concerns, aligned with the current codebase.

## Architecture Overview

The Chatbot package follows a clean, layered architecture that promotes maintainability, testability, and extensibility. The architecture is organized into distinct layers, each with specific responsibilities.

```
┌─────────────────────────────────────────────────────────────┐
│                      Application Layer                     │
│  (Examples, User Code, Framework Integrations)             │
└─────────────────────────────────────────────────────────────┘
┌─────────────────────────────────────────────────────────────┐
│                       Driver Layer                         │
│      (WebDriver, SlackDriver, custom drivers)              │
└─────────────────────────────────────────────────────────────┘
┌─────────────────────────────────────────────────────────────┐
│                        Core Layer                          │
│       (Bot, Matcher, Conversation, Context)                │
└─────────────────────────────────────────────────────────────┘
┌─────────────────────────────────────────────────────────────┐
│                      Storage Layer                         │
│      (ArrayStore, FileStore, Custom Stores)                │
└─────────────────────────────────────────────────────────────┘
┌─────────────────────────────────────────────────────────────┐
│                     Contract Layer                         │
│  (Interfaces: DriverInterface, StorageInterface)           │
└─────────────────────────────────────────────────────────────┘
```

## Layer Details

### 1. Contract Layer (Foundation)

Purpose: Defines interfaces and contracts that ensure consistency across implementations.

Components:
- DriverInterface: Contract for all messaging platform drivers
- StorageInterface: Contract for all storage implementations

Key Characteristics:
- No dependencies on other layers
- Pure interfaces with no implementation
- Framework-agnostic contracts
- Enables dependency injection and testing

Current DriverInterface (from src/Contracts/DriverInterface.php):
```php
namespace TusharKhan\Chatbot\Contracts;

interface DriverInterface
{
    /** Get the incoming message */
    public function getMessage(): ?string;

    /** Get the sender ID */
    public function getSenderId(): ?string;

    /** Send a message */
    public function sendMessage(string $message, ?string $senderId = null): bool;

    /** Get additional data from the driver */
    public function getData(): array;

    /** Check if there's an incoming message */
    public function hasMessage(): bool;
}
```

Benefits:
- Ensures consistent API across drivers
- Enables easy mocking for testing
- Supports multiple implementations of the same interface
- Facilitates dependency injection

### 2. Storage Layer

Purpose: Handles data persistence for conversations and bot state.

Components:
- ArrayStore: In-memory storage for development/testing
- FileStore: File-based storage for simple persistence
- Custom store implementations (database, Redis, etc.)

Key Characteristics:
- Implements StorageInterface
- Abstracted from storage medium details
- Configurable and swappable

Example (from src/Storage/FileStore.php - simplified signatures):
```php
class FileStore implements StorageInterface
{
    public function set(string $key, $value): bool {}
    public function get(string $key, $default = null) {}
    public function has(string $key): bool {}
    public function delete(string $key): bool {}
    public function clear(): bool {}

    public function getConversation(string $userId): array {}
    public function setConversation(string $userId, array $data): bool {}
    public function clearConversation(string $userId): bool {}
}
```

Design Patterns:
- Repository Pattern: Abstracts data access
- Strategy Pattern: Interchangeable storage strategies

### 3. Core Layer

Purpose: Contains the main business logic and orchestrates the chatbot functionality.

Components:
- Bot: Main orchestrator and entry point
- Matcher: Pattern matching and route resolution
- Conversation: Multi-turn conversation management
- Context: Request/response context and data sharing

Key Characteristics:
- Framework-agnostic business logic
- No knowledge of specific drivers or storage implementations
- Dependency injection for flexibility

Typical Responsibilities (see src/Core/Bot.php and src/Core/Matcher.php):
```php
class Bot
{
    public function hears($pattern, callable $handler): self {}
    public function fallback(callable $handler): self {}
    public function middleware(callable $middleware): self {}
    public function listen(): void {}
}

class Matcher
{
    public function match(string $message, $pattern): bool {}
    public function extractParams(string $message, $pattern): array {}
}
```

Interactions:
- Uses Storage Layer through StorageInterface
- Pulls messages from Driver Layer and routes them to handlers
- Manages Conversation and Context lifecycle

### 4. Driver Layer

Purpose: Handles platform-specific communication and message formatting.

Components (current drivers):
- WebDriver: HTTP-based web interface (JSON/form/query; supports CLI)
- SlackDriver: Slack Events, Slash Commands, Interactivity, rich messages
- Custom driver implementations

Key Characteristics:
- Platform-specific implementation details
- Handles authentication and API communication (e.g., Slack signing secret)
- Translates between platform formats and core Context

Common Driver Methods (from DriverInterface): getMessage, getSenderId, sendMessage, getData, hasMessage.

### 5. Application Layer

Purpose: User-facing code that uses the chatbot package.

Components:
- Example implementations in examples/
- Framework integrations (e.g., Laravel route examples in docs)
- Custom user applications
- Configuration and setup code

Key Characteristics:
- Uses the package through public APIs
- Contains application-specific logic
- Handles framework integration concerns

## Data Flow Between Layers

Downward Dependencies
```
Application → Driver → Core → Storage → Contracts
```

Each layer only depends on layers below it, never above. This ensures:
- Clean separation of concerns
- Easy testing through mocking
- Flexible implementation swapping
- Clear dependency management

Upward Communication
```
Contracts ← Storage ← Core ← Driver ← Application
```

Lower layers communicate upward through:
- Return values and exceptions
- Method calls into the next layer (e.g., driver->sendMessage)

## Component Interactions (Aligned with Code)

Bot → Matcher Interaction
```php
if ($this->matcher->match($message, $pattern)) {
    $params = $this->matcher->extractParams($message, $pattern);
    // invoke handler with context
}
```

Bot → Storage Interaction
```php
// Conversation is loaded and saved via StorageInterface
$convData = $this->storage->getConversation($userId);
// ... after processing
$this->storage->setConversation($userId, $updatedConvData);
```

Bot → Driver Interaction
```php
// Handlers typically return a string, which Bot sends via driver
$this->driver->sendMessage($responseString, $userId);
```

## Design Principles

1. Separation of Concerns
- Each layer has a single, well-defined responsibility

2. Dependency Inversion
- High-level modules depend on abstractions (interfaces)

3. Open/Closed Principle
- Open for extension through interfaces

4. Single Responsibility Principle
- Each class has one reason to change

## Extension Points

Custom Driver Implementation (outline)
```php
use TusharKhan\Chatbot\Contracts\DriverInterface;

class MyDriver implements DriverInterface {
    public function getMessage(): ?string { /* ... */ }
    public function getSenderId(): ?string { /* ... */ }
    public function sendMessage(string $message, ?string $senderId = null): bool { /* ... */ }
    public function getData(): array { /* ... */ }
    public function hasMessage(): bool { /* ... */ }
}
```

Custom Storage Implementation (outline)
```php
use TusharKhan\Chatbot\Contracts\StorageInterface;

class RedisStore implements StorageInterface {
    // implement interface methods
}
```

## Testing Strategy

Unit Testing by Layer
- Storage Layer: Data persistence and retrieval testing
- Core Layer: Business logic and flow testing
- Driver Layer: Platform integration testing (with mocks)

Integration Testing
- Cross-layer interaction testing
- End-to-end message flow testing

Mock Strategy
```php
$mockDriver = $this->createMock(\TusharKhan\Chatbot\Contracts\DriverInterface::class);
```

## Performance & Security (Highlights)

- Driver Layer: API rate limiting (platform-specific)
- Core Layer: Efficient pattern matching and parameter extraction
- Storage Layer: JSON read/write efficiency (FileStore), or swap to faster stores
- Security: Input validation (drivers), Slack signature verification, proper escaping when rendering HTML

This layered architecture reflects the current implementation and should help you reason about extension points and responsibilities accurately.
