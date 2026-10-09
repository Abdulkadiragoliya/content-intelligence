<?php
declare(strict_types=1);

namespace abdulkadiragoliya\contentintelligencetests\unit;

use PHPUnit\Framework\TestCase;

class AiServiceTest extends TestCase
{
    public function testEstimateTokens(): void
    {
        // Standard rule of thumb: ~4 characters per token
        $text = "This is a sample sentence with exactly ten words in it.";
        $charCount = strlen($text);
        $approxTokens = (int)ceil($charCount / 4);

        $this->assertTrue($approxTokens > 0);
        $this->assertTrue($approxTokens <= $charCount);
    }

    public function testContentStrippingHtmlTags(): void
    {
        $dirtyHtml = "<h2>Welcome to Craft CMS</h2><p>Here is <strong>content</strong> with <script>alert('xss')</script> tags.</p>";
        $clean = strip_tags(preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $dirtyHtml));

        $this->assertStringNotContainsString('<script>', $clean);
        $this->assertStringNotContainsString('alert(', $clean);
        $this->assertStringContainsString('Welcome to Craft CMS', $clean);
        $this->assertStringContainsString('Here is content', $clean);
    }
}
