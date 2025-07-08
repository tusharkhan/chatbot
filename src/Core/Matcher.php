<?php

namespace TusharKhan\Chatbot\Core;

class Matcher
{
    /**
     * Match patterns against a message
     */
    public function match(string $message, $pattern): bool
    {
        if (is_string($pattern)) {
            return $this->matchString($message, $pattern);
        }

        if (is_array($pattern)) {
            return $this->matchArray($message, $pattern);
        }

        if (is_callable($pattern)) {
            return $this->matchCallable($message, $pattern);
        }

        return false;
    }

    /**
     * Extract parameters from a message based on pattern
     */
    public function extractParams(string $message, $pattern): array
    {
        if (is_string($pattern) && strpos($pattern, '{') !== false) {
            return $this->extractParamsFromString($message, $pattern);
        }

        if ($this->isRegex($pattern)) {
            return $this->extractParamsFromRegex($message, $pattern);
        }

        return [];
    }

    /**
     * Match string pattern
     */
    private function matchString(string $message, string $pattern): bool
    {
        // Check for regex first
        if ($this->isRegex($pattern)) {
            return $this->matchRegex($message, $pattern);
        }
        
        // Exact match
        if ($pattern === $message) {
            return true;
        }

        // Wildcard match
        if (strpos($pattern, '*') !== false) {
            $regexPattern = preg_quote($pattern, '/');
            $regexPattern = str_replace('\\*', '.*', $regexPattern);
            return preg_match('/^' . $regexPattern . '$/i', $message) === 1;
        }

        // Parameter pattern like "hello {name}"
        if (strpos($pattern, '{') !== false) {
            $regexPattern = $this->convertToRegex($pattern);
            // Try exact match first
            if (preg_match($regexPattern, $message) === 1) {
                return true;
            }
            // Try partial match
            $partialPattern = str_replace('$/i', '/i', $regexPattern);
            return preg_match($partialPattern, $message) === 1;
        }

        // Case-insensitive contains
        return stripos($message, $pattern) !== false;
    }

    /**
     * Match array of patterns
     */
    private function matchArray(string $message, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if ($this->match($message, $pattern)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Match using callable
     */
    private function matchCallable(string $message, callable $pattern): bool
    {
        return (bool) $pattern($message);
    }

    /**
     * Match regex pattern
     */
    private function matchRegex(string $message, string $pattern): bool
    {
        return preg_match($pattern, $message) === 1;
    }

    /**
     * Check if pattern is regex
     */
    private function isRegex($pattern): bool
    {
        if (!is_string($pattern)) {
            return false;
        }
        return @preg_match($pattern, '') !== false;
    }

    /**
     * Convert parameter pattern to regex
     */
    private function convertToRegex(string $pattern): string
    {
        // Escape special regex characters except our placeholders
        $regexPattern = preg_quote($pattern, '/');
        // Replace the escaped placeholders with capture groups
        $regexPattern = preg_replace('/\\\\\\{[^}]+\\\\\\}/', '([^\\s]+)', $regexPattern);
        return '/^' . $regexPattern . '$/i';
    }

    /**
     * Extract parameters from string pattern
     */
    private function extractParamsFromString(string $message, string $pattern): array
    {
        // Create regex pattern for matching
        $regexPattern = preg_quote($pattern, '/');
        $regexPattern = preg_replace('/\\\\\\{[^}]+\\\\\\}/', '([^\\s]+)', $regexPattern);
        
        // Try exact match first
        $exactPattern = '/^' . $regexPattern . '$/i';
        if (preg_match($exactPattern, $message, $matches)) {
            array_shift($matches); // Remove full match
            
            // Extract parameter names from original pattern
            if (preg_match_all('/\{([^}]+)\}/', $pattern, $paramNames)) {
                $paramNames = $paramNames[1];
                
                $params = [];
                foreach ($paramNames as $index => $name) {
                    $params[$name] = $matches[$index] ?? null;
                }
                
                return $params;
            }
        }
        
        // Try partial match (for patterns that should match at the beginning)
        $partialPattern = '/^' . $regexPattern . '/i';
        if (preg_match($partialPattern, $message, $matches)) {
            array_shift($matches); // Remove full match
            
            // Extract parameter names from original pattern
            if (preg_match_all('/\{([^}]+)\}/', $pattern, $paramNames)) {
                $paramNames = $paramNames[1];
                
                $params = [];
                foreach ($paramNames as $index => $name) {
                    $params[$name] = $matches[$index] ?? null;
                }
                
                return $params;
            }
        }

        return [];
    }

    /**
     * Extract parameters from regex pattern
     */
    private function extractParamsFromRegex(string $message, string $pattern): array
    {
        if (preg_match($pattern, $message, $matches)) {
            array_shift($matches); // Remove full match
            return $matches;
        }

        return [];
    }
}
