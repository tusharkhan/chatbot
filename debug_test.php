<?php

require_once __DIR__ . '/vendor/autoload.php';

use TusharKhan\Chatbot\Core\Matcher;

$matcher = new Matcher();

// Test 1
$params = $matcher->extractParams('hello john', 'hello {name}');
var_dump('Test 1:', $params);

// Test 2
$params = $matcher->extractParams('my name is jane doe', 'my name is {name}');
var_dump('Test 2:', $params);

// Test 3
$params = $matcher->extractParams('order 5 pizza', 'order {quantity} {item}');
var_dump('Test 3:', $params);

// Test matching too
var_dump('Match test 1:', $matcher->match('hello john', 'hello {name}'));
var_dump('Match test 2:', $matcher->match('my name is jane doe', 'my name is {name}'));
var_dump('Match test 3:', $matcher->match('order 5 pizza', 'order {quantity} {item}'));
