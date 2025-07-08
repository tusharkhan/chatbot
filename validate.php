<?php
/**
 * Package Validation Script
 * 
 * This script validates that the TusharKhan Chatbot package is working correctly.
 */

require_once __DIR__ . '/vendor/autoload.php';

use TusharKhan\Chatbot\Core\Bot;
use TusharKhan\Chatbot\Drivers\WebDriver;
use TusharKhan\Chatbot\Storage\ArrayStore;
use TusharKhan\Chatbot\Storage\FileStore;

echo "🤖 TusharKhan Chatbot Package Validation\n";
echo "=======================================\n\n";

// Test 1: Basic bot creation
echo "✅ Test 1: Basic bot creation\n";
try {
    $driver = new WebDriver();
    $storage = new ArrayStore();
    $bot = new Bot($driver, $storage);
    echo "   Bot created successfully\n\n";
} catch (Exception $e) {
    echo "   ❌ Failed: " . $e->getMessage() . "\n\n";
    exit(1);
}

// Test 2: Pattern matching
echo "✅ Test 2: Pattern matching\n";
try {
    $tests = [
        ['hello', 'hello', true],
        ['hello world', 'hello*', true],
        ['my name is John', 'my name is {name}', true],
        ['goodbye', 'hello', false],
        ['123', '/^\d+$/', true],
        ['abc', '/^\d+$/', false],
    ];
    
    $matcher = new \TusharKhan\Chatbot\Core\Matcher();
    foreach ($tests as $test) {
        $result = $matcher->match($test[0], $test[1]);
        if ($result === $test[2]) {
            echo "   ✓ '{$test[0]}' vs '{$test[1]}' = " . ($result ? 'true' : 'false') . "\n";
        } else {
            echo "   ❌ '{$test[0]}' vs '{$test[1]}' expected " . ($test[2] ? 'true' : 'false') . " but got " . ($result ? 'true' : 'false') . "\n";
            exit(1);
        }
    }
    echo "\n";
} catch (Exception $e) {
    echo "   ❌ Failed: " . $e->getMessage() . "\n\n";
    exit(1);
}

// Test 3: Parameter extraction
echo "✅ Test 3: Parameter extraction\n";
try {
    $matcher = new \TusharKhan\Chatbot\Core\Matcher();
    $params = $matcher->extractParams('hello John', 'hello {name}');
    if ($params['name'] === 'John') {
        echo "   ✓ Parameter extraction: name = 'John'\n";
    } else {
        echo "   ❌ Expected 'John', got '{$params['name']}'\n";
        exit(1);
    }
    
    $params = $matcher->extractParams('order 5 pizza', 'order {quantity} {item}');
    if ($params['quantity'] === '5' && $params['item'] === 'pizza') {
        echo "   ✓ Multiple parameters: quantity = '5', item = 'pizza'\n";
    } else {
        echo "   ❌ Multiple parameter extraction failed\n";
        exit(1);
    }
    echo "\n";
} catch (Exception $e) {
    echo "   ❌ Failed: " . $e->getMessage() . "\n\n";
    exit(1);
}

// Test 4: Storage systems
echo "✅ Test 4: Storage systems\n";
try {
    // Array store
    $arrayStore = new ArrayStore();
    $arrayStore->set('test_key', 'test_value');
    if ($arrayStore->get('test_key') === 'test_value') {
        echo "   ✓ ArrayStore working\n";
    } else {
        echo "   ❌ ArrayStore failed\n";
        exit(1);
    }
    
    // File store
    $tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'chatbot_validation_' . uniqid();
    $fileStore = new FileStore($tempDir);
    $fileStore->set('test_key', 'test_value');
    if ($fileStore->get('test_key') === 'test_value') {
        echo "   ✓ FileStore working\n";
    } else {
        echo "   ❌ FileStore failed\n";
        exit(1);
    }
    
    // Cleanup
    if (is_dir($tempDir)) {
        array_map('unlink', glob($tempDir . DIRECTORY_SEPARATOR . '*'));
        rmdir($tempDir);
    }
    echo "\n";
} catch (Exception $e) {
    echo "   ❌ Failed: " . $e->getMessage() . "\n\n";
    exit(1);
}

// Test 5: Conversation management
echo "✅ Test 5: Conversation management\n";
try {
    $storage = new ArrayStore();
    $conversation = new \TusharKhan\Chatbot\Core\Conversation($storage, 'test_user');
    
    $conversation->setState('ordering');
    $conversation->set('name', 'John');
    $conversation->addMessage('user', 'Hello');
    
    if ($conversation->getState() === 'ordering' && 
        $conversation->get('name') === 'John' && 
        count($conversation->getHistory()) === 1) {
        echo "   ✓ Conversation state, variables, and history working\n";
    } else {
        echo "   ❌ Conversation management failed\n";
        exit(1);
    }
    echo "\n";
} catch (Exception $e) {
    echo "   ❌ Failed: " . $e->getMessage() . "\n\n";
    exit(1);
}

// Test 6: Bot functionality with mock data
echo "✅ Test 6: Bot functionality\n";
try {
    // Simulate a web request
    $_POST['message'] = 'hello world';
    $_POST['sender_id'] = 'test_user';
    
    $driver = new WebDriver();
    $bot = new Bot($driver);
    
    $handlerExecuted = false;
    $bot->hears('hello*', function($context) use (&$handlerExecuted) {
        $handlerExecuted = true;
        return 'Hello there!';
    });
    
    $bot->listen();
    
    if ($handlerExecuted && count($driver->getResponses()) === 1) {
        echo "   ✓ Bot message handling working\n";
    } else {
        echo "   ❌ Bot functionality failed\n";
        exit(1);
    }
    
    // Clean up
    $_POST = [];
    echo "\n";
} catch (Exception $e) {
    echo "   ❌ Failed: " . $e->getMessage() . "\n\n";
    exit(1);
}

echo "🎉 All tests passed! The TusharKhan Chatbot package is working correctly.\n\n";
echo "📚 Next steps:\n";
echo "   1. Check out the examples/ directory for usage examples\n";
echo "   2. Read the README.md for detailed documentation\n";
echo "   3. Run 'composer test' to execute the full test suite\n";
echo "   4. Start building your own chatbot!\n\n";
echo "🌟 Happy coding!\n";
