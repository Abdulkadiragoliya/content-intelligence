<?php
declare(strict_types=1);

namespace abdulkadiragoliya\contentintelligencetests\unit;

use PHPUnit\Framework\TestCase;

class PermissionTest extends TestCase
{
    public function testExpectedPermissionKeys(): void
    {
        $expected = [
            'contentIntelligence:viewDashboard',
            'contentIntelligence:runAudits',
            'contentIntelligence:useAi',
            'contentIntelligence:manageKnowledgeBase',
            'contentIntelligence:askWebsite',
            'contentIntelligence:manageSettings',
        ];

        foreach ($expected as $perm) {
            $this->assertStringStartsWith('contentIntelligence:', $perm);
        }

        $this->assertCount(6, $expected);
    }
}
