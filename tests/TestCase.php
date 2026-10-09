<?php
declare(strict_types=1);

namespace PHPUnit\Framework;

if (!class_exists(\PHPUnit\Framework\TestCase::class)) {
    abstract class TestCase
    {
        public function assertSame(mixed $expected, mixed $actual, string $message = ''): void
        {
            if ($expected !== $actual) {
                throw new \AssertionError("Failed asserting that " . var_export($actual, true) . " is identical to " . var_export($expected, true) . ($message ? " - {$message}" : ''));
            }
        }

        public function assertNotSame(mixed $expected, mixed $actual, string $message = ''): void
        {
            if ($expected === $actual) {
                throw new \AssertionError("Failed asserting that two values are not identical" . ($message ? " - {$message}" : ''));
            }
        }

        public function assertTrue(mixed $condition, string $message = ''): void
        {
            if ($condition !== true) {
                throw new \AssertionError("Failed asserting that condition is true" . ($message ? " - {$message}" : ''));
            }
        }

        public function assertFalse(mixed $condition, string $message = ''): void
        {
            if ($condition !== false) {
                throw new \AssertionError("Failed asserting that condition is false" . ($message ? " - {$message}" : ''));
            }
        }

        public function assertInstanceOf(string $expected, mixed $actual, string $message = ''): void
        {
            if (!($actual instanceof $expected)) {
                $actualType = is_object($actual) ? get_class($actual) : gettype($actual);
                throw new \AssertionError("Failed asserting that {$actualType} is an instance of {$expected}" . ($message ? " - {$message}" : ''));
            }
        }

        public function assertArrayHasKey(string|int $key, array $array, string $message = ''): void
        {
            if (!array_key_exists($key, $array)) {
                throw new \AssertionError("Failed asserting that array has key '{$key}'" . ($message ? " - {$message}" : ''));
            }
        }

        public function assertStringStartsWith(string $prefix, string $string, string $message = ''): void
        {
            if (!str_starts_with($string, $prefix)) {
                throw new \AssertionError("Failed asserting that '{$string}' starts with '{$prefix}'" . ($message ? " - {$message}" : ''));
            }
        }

        public function assertStringEndsWith(string $suffix, string $string, string $message = ''): void
        {
            if (!str_ends_with($string, $suffix)) {
                throw new \AssertionError("Failed asserting that '{$string}' ends with '{$suffix}'" . ($message ? " - {$message}" : ''));
            }
        }

        public function assertStringContainsString(string $needle, string $haystack, string $message = ''): void
        {
            if (!str_contains($haystack, $needle)) {
                throw new \AssertionError("Failed asserting that '{$haystack}' contains '{$needle}'" . ($message ? " - {$message}" : ''));
            }
        }

        public function assertStringNotContainsString(string $needle, string $haystack, string $message = ''): void
        {
            if (str_contains($haystack, $needle)) {
                throw new \AssertionError("Failed asserting that '{$haystack}' does not contain '{$needle}'" . ($message ? " - {$message}" : ''));
            }
        }

        public function assertCount(int $expectedCount, countable|array $countable, string $message = ''): void
        {
            $actualCount = count($countable);
            if ($actualCount !== $expectedCount) {
                throw new \AssertionError("Failed asserting that count {$actualCount} equals expected {$expectedCount}" . ($message ? " - {$message}" : ''));
            }
        }

        public function assertMatchesRegularExpression(string $pattern, string $string, string $message = ''): void
        {
            if (preg_match($pattern, $string) !== 1) {
                throw new \AssertionError("Failed asserting that '{$string}' matches regular expression '{$pattern}'" . ($message ? " - {$message}" : ''));
            }
        }
    }
}
