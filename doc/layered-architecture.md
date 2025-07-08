# Layered Architecture

This document describes the layered architecture of the Chatbot package, explaining how different components interact and the separation of concerns.

## Architecture Overview

The Chatbot package follows a clean, layered architecture that promotes maintainability, testability, and extensibility. The architecture is organized into distinct layers, each with specific responsibilities.

```
┌─────────────────────────────────────────────────────────────┐
│                      Application Layer                     │
│  (Examples, User Code, Framework Integrations)            │
└─────────────────────────────────────────────────────────────┘
┌─────────────────────────────────────────────────────────────┐
│                       Driver Layer                         │
│     (WebDriver, TelegramDriver, WhatsAppDriver)           │
└─────────────────────────────────────────────────────────────┘
┌─────────────────────────────────────────────────────────────┐
│                        Core Layer                          │
│       (Bot, Matcher, Conversation, Context)               │
└─────────────────────────────────────────────────────────────┘
┌─────────────────────────────────────────────────────────────┐
│                      Storage Layer                         │
│      (ArrayStore, FileStore, Custom Stores)              │
└─────────────────────────────────────────────────────────────┘
┌─────────────────────────────────────────────────────────────┐
│                     Contract Layer                         │
│  (Interfaces: DriverInterface, StorageInterface, etc.)    │
└─────────────────────────────────────────────────────────────┘
```

## Layer Details

### 1. Contract Layer (Foundation)

**Purpose**: Defines interfaces and contracts that ensure consistency across implementations.

**Components**:
- `DriverInterface`: Contract for all messaging platform drivers
- `StorageInterface`: Contract for all storage implementations
- `MatcherInterface`: Contract for message matching implementations

**Key Characteristics**:
- No dependencies on other layers
- Pure interfaces with no implementation
- Framework-agnostic contracts
- Enables dependency injection and testing

```php
namespace TusharKhan\Chatbot\Contracts;

interface DriverInterface
{
    public function listen(callable $callback): void;
    public function reply(string $message, Context $context): void;
}
```

**Benefits**:
- Ensures consistent API across implementations
- Enables easy mocking for testing
- Supports multiple implementations of same interface
- Facilitates dependency injection

### 2. Storage Layer

**Purpose**: Handles data persistence for conversations and bot state.

**Components**:
- `ArrayStore`: In-memory storage for development/testing
- `FileStore`: File-based storage for simple persistence
- Custom store implementations (database, Redis, etc.)

**Key Characteristics**:
- Implements `StorageInterface`
- Abstracted from storage medium details
- Thread-safe operations where applicable
- Configurable and swappable

```php
namespace TusharKhan\Chatbot\Storage;

class FileStore implements StorageInterface
{
    public function get(string $key): ?array
    public function put(string $key, array $data): bool
    public function has(string $key): bool
    public function forget(string $key): bool
}
```

**Design Patterns**:
- **Repository Pattern**: Abstracts data access
- **Strategy Pattern**: Interchangeable storage strategies
- **Factory Pattern**: Storage creation and configuration

### 3. Core Layer

**Purpose**: Contains the main business logic and orchestrates the chatbot functionality.

**Components**:
- `Bot`: Main orchestrator and entry point
- `Matcher`: Pattern matching and route resolution
- `Conversation`: Multi-turn conversation management
- `Context`: Request/response context and data sharing

**Key Characteristics**:
- Framework-agnostic business logic
- No knowledge of specific drivers or storage
- Dependency injection for flexibility
- Comprehensive error handling

```php
namespace TusharKhan\Chatbot\Core;

class Bot
{
    private MatcherInterface $matcher;
    private StorageInterface $storage;
    
    public function processMessage(Context $context): string
    {
        // Core business logic
    }
}
```

**Interactions**:
- Uses Storage Layer through `StorageInterface`
- Used by Driver Layer for message processing
- Manages Conversation and Context lifecycle

### 4. Driver Layer

**Purpose**: Handles platform-specific communication and message formatting.

**Components**:
- `WebDriver`: HTTP-based web interface
- `TelegramDriver`: Telegram Bot API integration
- `WhatsAppDriver`: WhatsApp Business API integration
- Custom driver implementations

**Key Characteristics**:
- Platform-specific implementation details
- Handles authentication and API communication
- Translates between platform formats and core Context
- Error handling for network and API issues

```php
namespace TusharKhan\Chatbot\Drivers;

class TelegramDriver implements DriverInterface
{
    public function listen(callable $callback): void
    {
        // Telegram webhook/polling logic
        $context = $this->createContext($telegramUpdate);
        $response = $callback($context);
        $this->reply($response, $context);
    }
}
```

**Design Patterns**:
- **Adapter Pattern**: Adapts platform APIs to common interface
- **Template Method**: Common driver workflow with platform-specific steps
- **Observer Pattern**: Event-driven message handling

### 5. Application Layer

**Purpose**: User-facing code that uses the chatbot package.

**Components**:
- Example implementations
- Framework integrations (Laravel, Symfony, etc.)
- Custom user applications
- Configuration and setup code

**Key Characteristics**:
- Uses the package through public APIs
- Contains application-specific logic
- Handles framework integration concerns
- Manages configuration and environment setup

## Data Flow Between Layers

### Downward Dependencies
```
Application → Driver → Core → Storage → Contracts
```

Each layer only depends on layers below it, never above. This ensures:
- Clean separation of concerns
- Easy testing through mocking
- Flexible implementation swapping
- Clear dependency management

### Upward Communication
```
Contracts ← Storage ← Core ← Driver ← Application
```

Lower layers communicate upward through:
- Return values and exceptions
- Callback functions
- Event dispatching (potential future enhancement)

## Component Interactions

### Bot → Matcher Interaction
```php
// Bot delegates pattern matching to Matcher
$match = $this->matcher->match($message);
if ($match) {
    $handler = $match['handler'];
    $params = $match['params'];
}
```

### Bot → Storage Interaction
```php
// Bot uses storage for conversation persistence
$conversationData = $this->storage->get($userId);
$conversation = Conversation::fromArray($conversationData);

// After processing
$this->storage->put($userId, $conversation->toArray());
```

### Driver → Bot Interaction
```php
// Driver creates context and delegates to Bot
$context = new Context($messageData);
$response = $this->bot->processMessage($context);
$this->reply($response, $context);
```

## Design Principles

### 1. Separation of Concerns
- Each layer has a single, well-defined responsibility
- No layer contains logic that belongs in another layer
- Clear boundaries between components

### 2. Dependency Inversion
- High-level modules don't depend on low-level modules
- Both depend on abstractions (interfaces)
- Enables flexible implementation swapping

### 3. Open/Closed Principle
- Open for extension through interfaces
- Closed for modification of core functionality
- New drivers and storage backends can be added without changing core

### 4. Single Responsibility Principle
- Each class has one reason to change
- Clear, focused responsibilities
- Easy to understand and maintain

## Extension Points

### Custom Driver Implementation
```php
class SlackDriver implements DriverInterface
{
    public function listen(callable $callback): void
    {
        // Slack-specific implementation
    }
    
    public function reply(string $message, Context $context): void
    {
        // Slack message formatting and sending
    }
}
```

### Custom Storage Implementation
```php
class RedisStore implements StorageInterface
{
    public function get(string $key): ?array
    {
        // Redis-specific implementation
    }
    
    // Other interface methods...
}
```

### Custom Matcher Implementation
```php
class AIMatcher implements MatcherInterface
{
    public function match(string $message): ?array
    {
        // AI-powered intent recognition
    }
    
    // Other interface methods...
}
```

## Testing Strategy

### Unit Testing by Layer
- **Contract Layer**: Interface compliance testing
- **Storage Layer**: Data persistence and retrieval testing
- **Core Layer**: Business logic and flow testing
- **Driver Layer**: Platform integration testing (with mocks)

### Integration Testing
- Cross-layer interaction testing
- End-to-end message flow testing
- Real storage backend testing

### Mock Strategy
```php
// Example: Testing Bot with mocked dependencies
$mockStorage = $this->createMock(StorageInterface::class);
$mockMatcher = $this->createMock(MatcherInterface::class);

$bot = new Bot($mockMatcher, $mockStorage);
```

## Performance Considerations

### Layer Optimization
- **Driver Layer**: Connection pooling, API rate limiting
- **Core Layer**: Efficient pattern matching, conversation caching
- **Storage Layer**: Optimized queries, connection management

### Memory Management
- Minimal object creation
- Proper resource cleanup
- Lazy loading where appropriate

## Security Considerations

### Layer-Specific Security
- **Driver Layer**: Input validation, authentication
- **Core Layer**: Business logic validation
- **Storage Layer**: Data encryption, access control

### Cross-Cutting Concerns
- Logging and auditing
- Error handling and sanitization
- Rate limiting and DoS protection

This layered architecture ensures the chatbot package is maintainable, extensible, and testable while providing clear separation of concerns and flexibility for different use cases.
