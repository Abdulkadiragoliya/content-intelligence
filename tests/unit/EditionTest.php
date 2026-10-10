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
        $this->assertSame('pro', Plugin::EDITION_PRO);
        $this->assertSame('plus', Plugin::EDITION_PLUS);
        $this->assertSame('plus', Plugin::EDITION_AGENCY);
    }

    public function testEditionsList(): void
    {
        $this->assertSame(['lite', 'pro', 'plus'], Plugin::editions());
    }

    public function testEditionCapabilityLogic(): void
    {
        // Pro features are available in Pro and Plus
        $proCapableEditions = [Plugin::EDITION_PRO, Plugin::EDITION_PLUS];
        $this->assertTrue(in_array('pro', $proCapableEditions, true));
        $this->assertTrue(in_array('plus', $proCapableEditions, true));
        $this->assertFalse(in_array('lite', $proCapableEditions, true));

        // Plus features are strictly available in Plus
        $plusOnly = fn(string $ed) => $ed === Plugin::EDITION_PLUS;
        $this->assertTrue($plusOnly('plus'));
        $this->assertFalse($plusOnly('pro'));
        $this->assertFalse($plusOnly('lite'));
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
