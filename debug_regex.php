<?php

require_once __DIR__ . '/vendor/autoload.php';

$pattern = 'my name is {name}';
$message = 'my name is jane doe';

echo "Original pattern: $pattern\n";
echo "Message: $message\n";

// Test the conversion
$regexPattern = preg_quote($pattern, '/');
echo "After preg_quote: $regexPattern\n";

$regexPattern = preg_replace('/\\\\\\{[^}]+\\\\\\}/', '([^\\s]+)', $regexPattern);
echo "After replacement: $regexPattern\n";

$regexPattern = '/^' . $regexPattern . '$/i';
echo "Final regex: $regexPattern\n";

$result = preg_match($regexPattern, $message, $matches);
echo "Match result: $result\n";
var_dump($matches);

// Test with a working message
$workingMessage = 'my name is jane';
echo "\nTesting with working message: $workingMessage\n";
$result = preg_match($regexPattern, $workingMessage, $matches);
echo "Match result: $result\n";
var_dump($matches);
