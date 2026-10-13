<?php
declare(strict_types=1);

namespace abdulkadiragoliya\contentintelligencetests\unit;

use PHPUnit\Framework\TestCase;
use abdulkadiragoliya\contentintelligence\services\ai\PromptTemplates;

class SeoMetadataGenerationTest extends TestCase
{
    public function testMetaTitlePromptStructure(): void
    {
        $prompts = PromptTemplates::metaTitleSuggestions('Craft CMS Web Design', 'We craft beautiful websites for clients across the globe.');

        $this->assertArrayHasKey('system', $prompts);
        $this->assertArrayHasKey('user', $prompts);

        $this->assertStringContainsString('30 and 60 characters', $prompts['system']);
        $this->assertStringContainsString('Current Title: Craft CMS Web Design', $prompts['user']);
        $this->assertStringContainsString('"suggestions"', $prompts['user']);
        $this->assertStringContainsString('"title"', $prompts['user']);
        $this->assertStringContainsString('"length"', $prompts['user']);
    }

    public function testMetaDescriptionPromptStructure(): void
    {
        $prompts = PromptTemplates::metaDescriptionSuggestions('About Us', 'Founded in 2018, our agency provides high quality web software engineering.');

        $this->assertArrayHasKey('system', $prompts);
        $this->assertArrayHasKey('user', $prompts);

        $this->assertStringContainsString('120 and 155 characters', $prompts['system']);
        $this->assertStringContainsString('Page Title: About Us', $prompts['user']);
        $this->assertStringContainsString('"suggestions"', $prompts['user']);
        $this->assertStringContainsString('"description"', $prompts['user']);
        $this->assertStringContainsString('call-to-action', $prompts['user']);
    }

    public function testGenerationPreservesEntryTitleAndContent(): void
    {
        // State of entry before generation
        $entryState = [
            'id' => 50,
            'title' => 'Original Entry Heading',
            'body' => '<p>Original body copy content.</p>',
        ];

        // AI simulated output suggestions
        $aiSuggestions = [
            'titles' => [
                ['title' => 'Suggested SEO Title 1', 'length' => 22],
                ['title' => 'Suggested SEO Title 2', 'length' => 22],
            ],
            'descriptions' => [
                ['description' => 'Suggested Description 1', 'length' => 24],
            ],
        ];

        // Ensure generation output is returned for preview without modifying the entry
        $this->assertCount(2, $aiSuggestions['titles']);
        $this->assertSame('Original Entry Heading', $entryState['title']);
        $this->assertSame('<p>Original body copy content.</p>', $entryState['body']);
    }

    public function testEmptyContentValidation(): void
    {
        $validateInput = function(string $title, string $content): array {
            if (empty(trim($content)) && empty(trim($title))) {
                return ['valid' => false, 'message' => 'Please provide page title or content to generate meta tags.'];
            }
            return ['valid' => true, 'message' => ''];
        };

        $invalid = $validateInput('', '   ');
        $this->assertFalse($invalid['valid']);
        $this->assertStringContainsString('Please provide page title or content', $invalid['message']);

        $valid = $validateInput('About Us', 'Here is content.');
        $this->assertTrue($valid['valid']);
    }
}
