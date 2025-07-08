# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2025-07-09

### Added
- Initial release of TusharKhan Chatbot package
- Framework-agnostic PHP chatbot with multi-platform support
- Core Bot class with message routing and pattern matching
- Matcher class supporting multiple pattern types:
  - Exact string matching
  - Wildcard patterns with *
  - Parameter extraction with {param} syntax
  - Regular expressions
  - Array of patterns
  - Custom callable matchers
- Conversation management with state and variables
- Multi-turn conversation support with history tracking
- Storage layer with interfaces:
  - ArrayStore (in-memory storage)
  - FileStore (persistent file-based storage)
- Driver support for multiple platforms:
  - WebDriver for web-based chatbots
  - TelegramDriver for Telegram bots
  - WhatsAppDriver for WhatsApp Business API
- Middleware support for custom processing
- Fallback handler for unmatched messages
- Comprehensive test suite with 44 tests and 92 assertions
- Complete documentation and examples
- PSR-12 coding standards compliance
- Composer package with autoloading

### Features
- **Pattern Matching**: Flexible message routing with parameters, wildcards, and regex
- **Multi-Platform**: Works with Web, Telegram, and WhatsApp
- **Framework Agnostic**: Compatible with plain PHP, Laravel, CodeIgniter, etc.
- **Conversation State**: Track user conversations with state management
- **Storage Options**: File-based or in-memory storage (easily extensible)
- **Middleware**: Add custom processing logic
- **Error Handling**: Graceful fallback for unmatched messages
- **Testing**: Full unit test coverage

### Examples
- Simple web chatbot example
- Complete web interface with HTML/CSS/JavaScript
- Telegram bot with commands and keyboards
- WhatsApp Business bot with templates and media
- Framework integration examples for Laravel and CodeIgniter

### Documentation
- Comprehensive README with usage examples
- API reference documentation
- Installation and setup guides
- Best practices and patterns
- Contributing guidelines
