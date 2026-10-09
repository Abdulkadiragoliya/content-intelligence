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
        $this->assertSame('agency', Plugin::EDITION_AGENCY);
    }

    public function testEditionCapabilityLogic(): void
    {
        // Pro features are available in Pro and Agency
        $proCapableEditions = [Plugin::EDITION_PRO, Plugin::EDITION_AGENCY];
        $this->assertTrue(in_array('pro', $proCapableEditions, true));
        $this->assertTrue(in_array('agency', $proCapableEditions, true));
        $this->assertFalse(in_array('lite', $proCapableEditions, true));

        // Agency features are strictly available in Agency
        $agencyOnly = fn(string $ed) => $ed === Plugin::EDITION_AGENCY;
        $this->assertTrue($agencyOnly('agency'));
        $this->assertFalse($agencyOnly('pro'));
        $this->assertFalse($agencyOnly('lite'));
    }
}
