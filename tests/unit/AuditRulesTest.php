<?php
declare(strict_types=1);

namespace abdulkadiragoliya\contentintelligencetests\unit;

use PHPUnit\Framework\TestCase;
use craft\elements\Entry;
use abdulkadiragoliya\contentintelligence\models\AuditResult;
use abdulkadiragoliya\contentintelligence\services\audit\rules\MissingTitleRule;

class AuditRulesTest extends TestCase
{
    public function testMissingTitleRuleCriticalOnEmpty(): void
    {
        $rule = new MissingTitleRule();
        $entry = new Entry();
        $entry->title = '';

        $result = $rule->run($entry);
        $this->assertInstanceOf(AuditResult::class, $result);
        $this->assertSame(AuditResult::SEVERITY_CRITICAL, $result->severity);
        $this->assertSame('Missing Title', $result->title);
    }

    public function testMissingTitleRuleWarningOnPlaceholder(): void
    {
        $rule = new MissingTitleRule();
        $entry = new Entry();
        $entry->title = 'Untitled Entry';

        $result = $rule->run($entry);
        $this->assertInstanceOf(AuditResult::class, $result);
        $this->assertSame(AuditResult::SEVERITY_WARNING, $result->severity);
        $this->assertStringContainsString('Placeholder', $result->title);
    }

    public function testMissingTitleRuleGoodOnValidTitle(): void
    {
        $rule = new MissingTitleRule();
        $entry = new Entry();
        $entry->title = 'Comprehensive Guide to Craft CMS 5 Plugins';

        $result = $rule->run($entry);
        $this->assertInstanceOf(AuditResult::class, $result);
        $this->assertSame(AuditResult::SEVERITY_GOOD, $result->severity);
    }
}
