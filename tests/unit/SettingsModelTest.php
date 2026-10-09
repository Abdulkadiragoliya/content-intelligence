<?php
declare(strict_types=1);

namespace abdulkadiragoliya\contentintelligencetests\unit;

use PHPUnit\Framework\TestCase;
use abdulkadiragoliya\contentintelligence\models\Settings;

class SettingsModelTest extends TestCase
{
    public function testDefaultValues(): void
    {
        $settings = new Settings();

        $this->assertSame(300, $settings->minContentWordCount);
        $this->assertSame(180, $settings->staleContentDays);
        $this->assertSame(30, $settings->minTitleLength);
        $this->assertSame(60, $settings->maxTitleLength);
        $this->assertSame('openai', $settings->aiProvider);
        $this->assertSame('text-embedding-3-small', $settings->embeddingModel);
    }

    public function testValidationRules(): void
    {
        $settings = new Settings();
        $this->assertTrue($settings->validate());

        $settings->temperature = 1.5; // max is 1.0
        $this->assertFalse($settings->validate());
        $this->assertArrayHasKey('temperature', $settings->getErrors());
    }

    public function testMaskedKey(): void
    {
        $settings = new Settings();
        $masked = $settings->getMaskedKey('sk-1234567890abcdef');
        $this->assertStringStartsWith('sk-', $masked);
        $this->assertStringEndsWith('cdef', $masked);
        $this->assertStringContainsString('***', $masked);
    }

    public function testSiteSettingsOverride(): void
    {
        $settings = new Settings();
        $settings->minContentWordCount = 300;
        $settings->siteSettings = [
            2 => ['minContentWordCount' => 500],
        ];

        // Global fallback for site 1
        $this->assertSame(300, $settings->getForSite('minContentWordCount', 1));
        // Overridden value for site 2
        $this->assertSame(500, $settings->getForSite('minContentWordCount', 2));
    }
}
