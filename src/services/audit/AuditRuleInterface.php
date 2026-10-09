<?php

namespace abdulkadiragoliya\contentintelligence\services\audit;

use craft\elements\Entry;
use abdulkadiragoliya\contentintelligence\models\AuditResult;

/**
 * Interface that all Content Intelligence audit rules must implement.
 */
interface AuditRuleInterface
{
    /**
     * Unique identifier for this rule (e.g. 'thin_content', 'heading_hierarchy').
     */
    public function getId(): string;

    /**
     * Rule category: 'content' or 'seo'.
     */
    public function getCategory(): string;

    /**
     * Human-readable name of the rule.
     */
    public function getName(): string;

    /**
     * Executes the audit check against a Craft Entry element.
     *
     * @param Entry $entry
     * @return AuditResult|null
     */
    public function run(Entry $entry): ?AuditResult;
}
