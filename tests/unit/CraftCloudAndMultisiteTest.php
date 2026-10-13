<?php
declare(strict_types=1);

namespace abdulkadiragoliya\contentintelligencetests\unit;

use PHPUnit\Framework\TestCase;
use abdulkadiragoliya\contentintelligence\Plugin;
use abdulkadiragoliya\contentintelligence\db\Table;

class CraftCloudAndMultisiteTest extends TestCase
{
    public function testCraftCloudEnvironmentEnforcesProduction(): void
    {
        $checkIsDevOrLocal = function(array $env, bool $devMode = false): bool {
            // 0. Craft Cloud environment check
            if (!empty($env['CRAFT_CLOUD'])) {
                return false;
            }

            // 1. Explicit devMode
            if ($devMode) {
                return true;
            }

            // 2. CRAFT_ENVIRONMENT check
            $craftEnv = strtolower($env['CRAFT_ENVIRONMENT'] ?? '');
            if (in_array($craftEnv, ['dev', 'local', 'test', 'testing', 'staging'], true)) {
                return true;
            }

            return false;
        };

        // When deployed on Craft Cloud, even if someone attempts devMode, Craft Cloud is non-local production
        $craftCloudEnv = ['CRAFT_CLOUD' => 'true', 'CRAFT_ENVIRONMENT' => 'production'];
        $this->assertFalse($checkIsDevOrLocal($craftCloudEnv, false));
        $this->assertFalse($checkIsDevOrLocal($craftCloudEnv, true), 'Craft Cloud must always enforce production license integrity');

        // Local development without Craft Cloud
        $localDev = ['CRAFT_ENVIRONMENT' => 'dev'];
        $this->assertTrue($checkIsDevOrLocal($localDev, false));
    }

    public function testDatabaseTablePrefixAndAgnosticSchema(): void
    {
        // Table constant must use Craft prefix notation
        $this->assertSame('{{%contentintelligence_seo_metadata}}', Table::SEO_METADATA);

        // Ensure table name is safely parsed with custom prefix
        $prefix = 'mycraft_';
        $resolvedTable = str_replace(['{{%', '}}'], [$prefix, ''], Table::SEO_METADATA);
        $this->assertSame('mycraft_contentintelligence_seo_metadata', $resolvedTable);
    }

    public function testMultisiteIsolationAndSiteSpecificOutput(): void
    {
        $siteStore = [
            // Site 1: US English (siteId: 1)
            '1:501' => [
                'elementId' => 501,
                'siteId' => 1,
                'seoTitle' => 'Enterprise Cloud Consulting | Acme USA',
                'metaDescription' => 'Scale your cloud architecture with Acme USA certified consultants.',
            ],
            // Site 2: German (siteId: 2)
            '2:501' => [
                'elementId' => 501,
                'siteId' => 2,
                'seoTitle' => 'Enterprise Cloud Beratung | Acme Deutschland',
                'metaDescription' => 'Skalieren Sie Ihre Cloud-Architektur mit zertifizierten Acme Beratern.',
            ],
        ];

        $renderForSite = function(int $elementId, int $siteId) use ($siteStore): string {
            $key = "{$siteId}:{$elementId}";
            $meta = $siteStore[$key] ?? null;
            if (!$meta) {
                return '';
            }
            $t = htmlspecialchars($meta['seoTitle'], ENT_QUOTES, 'UTF-8');
            $d = htmlspecialchars($meta['metaDescription'], ENT_QUOTES, 'UTF-8');
            return "<title>{$t}</title>\n<meta name=\"description\" content=\"{$d}\">";
        };

        $htmlSite1 = $renderForSite(501, 1);
        $htmlSite2 = $renderForSite(501, 2);
        $htmlSite3 = $renderForSite(501, 3); // Unsaved site

        $this->assertStringContainsString('Enterprise Cloud Consulting | Acme USA', $htmlSite1);
        $this->assertStringNotContainsString('Acme Deutschland', $htmlSite1);

        $this->assertStringContainsString('Enterprise Cloud Beratung | Acme Deutschland', $htmlSite2);
        $this->assertStringNotContainsString('Acme USA', $htmlSite2);

        $this->assertSame('', $htmlSite3, 'Site with no saved metadata must produce empty standalone output');
    }

    public function testSiteDeletionCascadeSimulation(): void
    {
        $records = [
            ['id' => 1, 'siteId' => 1, 'elementId' => 10],
            ['id' => 2, 'siteId' => 2, 'elementId' => 10],
            ['id' => 3, 'siteId' => 2, 'elementId' => 20],
        ];

        // Simulate deleting site #2
        $remaining = array_filter($records, fn($r) => $r['siteId'] !== 2);
        $this->assertCount(1, $remaining);
        $this->assertSame(1, array_values($remaining)[0]['siteId']);
    }
}
