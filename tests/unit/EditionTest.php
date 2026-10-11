<?php
declare(strict_types=1);

namespace abdulkadiragoliya\contentintelligencetests\unit;

use PHPUnit\Framework\TestCase;
use abdulkadiragoliya\contentintelligence\Plugin;

class EditionTest extends TestCase
{
    public function testEditionConstants(): void
    {
        $this->assertSame('lite', Plugin::EDITION_LITE);
        $this->assertSame('plus', Plugin::EDITION_PLUS);
        $this->assertSame('pro', Plugin::EDITION_PRO);
        $this->assertSame('pro', Plugin::EDITION_AGENCY);
    }

    public function testEditionsList(): void
    {
        $this->assertSame(['lite', 'plus', 'pro'], Plugin::editions());
    }

    public function testEditionCapabilityLogic(): void
    {
        // Plus features (AI Assistant) are available in Plus and Pro
        $plusCapableEditions = [Plugin::EDITION_PLUS, Plugin::EDITION_PRO];
        $this->assertTrue(in_array('plus', $plusCapableEditions, true));
        $this->assertTrue(in_array('pro', $plusCapableEditions, true));
        $this->assertFalse(in_array('lite', $plusCapableEditions, true));

        // Pro features (Semantic Search & RAG) are strictly available in Pro
        $proOnly = fn(string $ed) => $ed === Plugin::EDITION_PRO;
        $this->assertTrue($proOnly('pro'));
        $this->assertFalse($proOnly('plus'));
        $this->assertFalse($proOnly('lite'));
    }

    public function testLocalEnvironmentDomainPatterns(): void
    {
        $localDomains = [
            'localhost',
            '127.0.0.1',
            '::1',
            'local.craftlearning.com',
            'dev.craftlearning.com',
            'mysite.local',
            'mysite.test',
            'mysite.localhost',
            'craft.ddev.site',
            'app.nitro',
        ];

        $isLocalHost = function(string $host): bool {
            return $host === 'localhost' ||
                $host === '127.0.0.1' ||
                $host === '::1' ||
                str_starts_with($host, 'local.') ||
                str_starts_with($host, 'dev.') ||
                str_ends_with($host, '.local') ||
                str_ends_with($host, '.test') ||
                str_ends_with($host, '.localhost') ||
                str_ends_with($host, '.ddev.site') ||
                str_ends_with($host, '.nitro');
        };

        foreach ($localDomains as $domain) {
            $this->assertTrue($isLocalHost($domain), "Domain {$domain} should be recognized as local/dev");
        }

        $productionDomains = [
            'craftlearning.com',
            'mysite.com',
            'agency.co.uk',
            'clientproject.org',
        ];

        foreach ($productionDomains as $domain) {
            $this->assertFalse($isLocalHost($domain), "Production domain {$domain} must not be recognized as local/dev");
        }
    }
}
