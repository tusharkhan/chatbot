<?php

namespace TusharKhan\Chatbot\Tests\Core;

use PHPUnit\Framework\TestCase;
use TusharKhan\Chatbot\Core\Matcher;

class MatcherTest extends TestCase
{
    private $matcher;

    protected function setUp(): void
    {
        $this->matcher = new Matcher();
    }

    public function testExactStringMatch()
    {
        $this->assertTrue($this->matcher->match('hello', 'hello'));
        $this->assertFalse($this->matcher->match('hello', 'hi'));
    }

    public function testWildcardMatch()
    {
        $this->assertTrue($this->matcher->match('hello world', 'hello*'));
        $this->assertTrue($this->matcher->match('hello', 'hello*'));
        $this->assertFalse($this->matcher->match('hi world', 'hello*'));
    }

    public function testParameterMatch()
    {
        $this->assertTrue($this->matcher->match('hello john', 'hello {name}'));
        $this->assertTrue($this->matcher->match('my name is jane', 'my name is {name}'));
        $this->assertFalse($this->matcher->match('hello', 'hello {name}'));
    }

    public function testArrayMatch()
    {
        $patterns = ['hello', 'hi', 'hey'];
        $this->assertTrue($this->matcher->match('hello', $patterns));
        $this->assertTrue($this->matcher->match('hi', $patterns));
        $this->assertFalse($this->matcher->match('goodbye', $patterns));
    }

    public function testCallableMatch()
    {
        $pattern = function ($message) {
            return strpos($message, 'test') !== false;
        };

        $this->assertTrue($this->matcher->match('this is a test', $pattern));
        $this->assertFalse($this->matcher->match('hello world', $pattern));
    }

    public function testRegexMatch()
    {
        $this->assertTrue($this->matcher->match('123', '/^\d+$/'));
        $this->assertFalse($this->matcher->match('abc', '/^\d+$/'));
    }

    public function testExtractParams()
    {
        $params = $this->matcher->extractParams('hello john', 'hello {name}');
        $this->assertEquals(['name' => 'john'], $params);

        $params = $this->matcher->extractParams('my name is jane doe', 'my name is {name}');
        $this->assertEquals(['name' => 'jane'], $params);

        $params = $this->matcher->extractParams('order 5 pizza', 'order {quantity} {item}');
        $this->assertEquals(['quantity' => '5', 'item' => 'pizza'], $params);
    }

    public function testExtractParamsFromRegex()
    {
        $params = $this->matcher->extractParams('123', '/^(\d+)$/');
        $this->assertEquals(['123'], $params);
    }

    public function testCaseInsensitiveMatch()
    {
        $this->assertTrue($this->matcher->match('HELLO', 'hello'));
        $this->assertTrue($this->matcher->match('Hello World', 'hello'));
    }
}
