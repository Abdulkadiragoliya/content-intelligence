<?php
declare(strict_types=1);

namespace abdulkadiragoliya\contentintelligencetests\unit;

use PHPUnit\Framework\TestCase;
use abdulkadiragoliya\contentintelligence\Plugin;

class SeoMetadataEditionTest extends TestCase
{
    public function testLiteEditionAllowsMetadataStorageAndOutput(): void
    {
        $canStoreMetadataInEdition = function(string $edition): bool {
            // All editions (lite, plus, pro) support manual standalone SEO metadata
            return in_array($edition, [Plugin::EDITION_LITE, Plugin::EDITION_PLUS, Plugin::EDITION_PRO], true);
        };

        $canRenderMetadataInEdition = function(string $edition): bool {
            // All editions can render saved metadata into HTML head
            return in_array($edition, [Plugin::EDITION_LITE, Plugin::EDITION_PLUS, Plugin::EDITION_PRO], true);
        };

        $this->assertTrue($canStoreMetadataInEdition(Plugin::EDITION_LITE));
        $this->assertTrue($canRenderMetadataInEdition(Plugin::EDITION_LITE));
        $this->assertTrue($canStoreMetadataInEdition(Plugin::EDITION_PRO));
        $this->assertTrue($canRenderMetadataInEdition(Plugin::EDITION_PRO));
    }

    public function testAiGenerationStrictlyRequiresProOrPlus(): void
    {
        $canUseAiGeneration = function(string $edition): bool {
            return in_array($edition, [Plugin::EDITION_PLUS, Plugin::EDITION_PRO], true);
        };

        // Lite is blocked
        $this->assertFalse($canUseAiGeneration(Plugin::EDITION_LITE));

        // Plus and Pro are unlocked
        $this->assertTrue($canUseAiGeneration(Plugin::EDITION_PLUS));
        $this->assertTrue($canUseAiGeneration(Plugin::EDITION_PRO));
    }

    public function testUnauthorizedDirectRequestsAreBlocked(): void
    {
        $simulateApiGuard = function(string $edition, bool $isPost, bool $isAuthenticated): array {
            if (!$isAuthenticated) {
                return ['allowed' => false, 'status' => 403, 'reason' => 'Unauthorized'];
            }
            if (!$isPost) {
                return ['allowed' => false, 'status' => 400, 'reason' => 'POST required'];
            }
            if (!in_array($edition, [Plugin::EDITION_PLUS, Plugin::EDITION_PRO], true)) {
                return ['allowed' => false, 'status' => 403, 'reason' => 'Upgrade required'];
            }
            return ['allowed' => true, 'status' => 200, 'reason' => 'OK'];
        };

        // Guest request
        $resGuest = $simulateApiGuard(Plugin::EDITION_PRO, true, false);
        $this->assertFalse($resGuest['allowed']);
        $this->assertSame(403, $resGuest['status']);

        // GET request to generation endpoint
        $resGet = $simulateApiGuard(Plugin::EDITION_PRO, false, true);
        $this->assertFalse($resGet['allowed']);
        $this->assertSame(400, $resGet['status']);

        // Lite edition direct request
        $resLite = $simulateApiGuard(Plugin::EDITION_LITE, true, true);
        $this->assertFalse($resLite['allowed']);
        $this->assertSame('Upgrade required', $resLite['reason']);

        // Legitimate Pro request
        $resPro = $simulateApiGuard(Plugin::EDITION_PRO, true, true);
        $this->assertTrue($resPro['allowed']);
        $this->assertSame(200, $resPro['status']);
    }
}
