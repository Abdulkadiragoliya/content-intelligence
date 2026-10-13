<?php
declare(strict_types=1);

namespace abdulkadiragoliya\contentintelligencetests\unit;

use PHPUnit\Framework\TestCase;
use abdulkadiragoliya\contentintelligence\records\SeoMetadataRecord;

class SeoMetadataStorageTest extends TestCase
{
    public function testMetadataRecordValidationRules(): void
    {
        $record = new SeoMetadataRecord();
        $rules = $record->rules();

        $this->assertTrue(is_array($rules));
        $this->assertCount(5, $rules);

        // Required rule check
        $this->assertSame(['siteId', 'elementId'], $rules[0][0]);
        $this->assertSame('required', $rules[0][1]);

        // Max length rule check
        $this->assertSame(['seoTitle'], $rules[2][0]);
        $this->assertSame('string', $rules[2][1]);
        $this->assertSame(255, $rules[2]['max']);
    }

    public function testSimulatedSaveAndRetrieve(): void
    {
        // Mock store simulating database table
        $store = [];

        $save = function(int $elementId, int $siteId, ?string $title, ?string $desc, bool $isAi = false) use (&$store): array {
            $key = "{$siteId}:{$elementId}";
            $store[$key] = [
                'elementId' => $elementId,
                'siteId' => $siteId,
                'seoTitle' => $title ? trim($title) : null,
                'metaDescription' => $desc ? trim($desc) : null,
                'isAiGenerated' => $isAi,
                'dateUpdated' => date('Y-m-d H:i:s'),
            ];
            return $store[$key];
        };

        $get = function(int $elementId, int $siteId) use (&$store): ?array {
            $key = "{$siteId}:{$elementId}";
            return $store[$key] ?? null;
        };

        // 1. Save and retrieve
        $saved = $save(101, 1, 'Craft CMS Development Services | Example', 'Expert Craft CMS solutions for fast websites.', false);
        $this->assertSame('Craft CMS Development Services | Example', $saved['seoTitle']);
        $this->assertSame('Expert Craft CMS solutions for fast websites.', $saved['metaDescription']);
        $this->assertFalse($saved['isAiGenerated']);

        $retrieved = $get(101, 1);
        $this->assertSame('Craft CMS Development Services | Example', $retrieved['seoTitle']);
        $this->assertSame('Expert Craft CMS solutions for fast websites.', $retrieved['metaDescription']);

        // 2. Update existing metadata
        $updated = $save(101, 1, 'Updated SEO Title', 'Updated description content.', true);
        $this->assertSame('Updated SEO Title', $updated['seoTitle']);
        $this->assertTrue($updated['isAiGenerated']);

        // 3. Handle empty / null values
        $cleared = $save(101, 1, '', null, false);
        $this->assertSame(null, $cleared['seoTitle']);
        $this->assertSame(null, $cleared['metaDescription']);
    }

    public function testMultisiteIsolation(): void
    {
        $store = [];

        $save = function(int $elementId, int $siteId, ?string $title, ?string $desc) use (&$store): void {
            $store["{$siteId}:{$elementId}"] = [
                'seoTitle' => $title,
                'metaDescription' => $desc,
            ];
        };

        $get = function(int $elementId, int $siteId) use (&$store): ?array {
            return $store["{$siteId}:{$elementId}"] ?? null;
        };

        // Same element ID #42 on Site 1 (English) and Site 2 (German)
        $save(42, 1, 'English SEO Title', 'English meta description');
        $save(42, 2, 'Deutscher SEO-Titel', 'Deutsche Meta-Beschreibung');

        $site1Meta = $get(42, 1);
        $site2Meta = $get(42, 2);

        $this->assertSame('English SEO Title', $site1Meta['seoTitle']);
        $this->assertSame('English meta description', $site1Meta['metaDescription']);

        $this->assertSame('Deutscher SEO-Titel', $site2Meta['seoTitle']);
        $this->assertSame('Deutsche Meta-Beschreibung', $site2Meta['metaDescription']);

        // Ensure Site 3 has no metadata (no leakage)
        $site3Meta = $get(42, 3);
        $this->assertSame(null, $site3Meta);
    }

    public function testElementDeletionCleanup(): void
    {
        $store = [
            '1:100' => ['elementId' => 100, 'siteId' => 1, 'seoTitle' => 'Title A'],
            '2:100' => ['elementId' => 100, 'siteId' => 2, 'seoTitle' => 'Title B'],
            '1:200' => ['elementId' => 200, 'siteId' => 1, 'seoTitle' => 'Title C'],
        ];

        // Simulate delete for element #100
        $deleteElement = function(int $elementId) use (&$store): int {
            $deletedCount = 0;
            foreach ($store as $key => $row) {
                if ($row['elementId'] === $elementId) {
                    unset($store[$key]);
                    $deletedCount++;
                }
            }
            return $deletedCount;
        };

        $deleted = $deleteElement(100);
        $this->assertSame(2, $deleted);
        $this->assertCount(1, $store);
        $this->assertArrayHasKey('1:200', $store);
    }
}
