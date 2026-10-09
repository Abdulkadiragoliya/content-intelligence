<?php

namespace abdulkadiragoliya\contentintelligence\services\audit\rules;

use craft\elements\Entry;
use abdulkadiragoliya\contentintelligence\Plugin;
use abdulkadiragoliya\contentintelligence\models\AuditResult;
use abdulkadiragoliya\contentintelligence\services\audit\BaseAuditRule;
use DateTime;

/**
 * Checks content freshness and detects stale entries requiring review.
 */
class StaleContentRule extends BaseAuditRule
{
    public function getId(): string
    {
        return 'stale_content';
    }

    public function getName(): string
    {
        return 'Content Freshness & Decay';
    }

    public function run(Entry $entry): ?AuditResult
    {
        $settings = Plugin::getInstance()->getSettings();
        $staleDays = $settings->staleContentDays ?? 180;

        if (!$entry->dateUpdated) {
            return null;
        }

        $now = new DateTime();
        $diffDays = $now->diff($entry->dateUpdated)->days;

        if ($diffDays > ($staleDays * 2)) {
            return $this->createResult(
                AuditResult::SEVERITY_WARNING,
                'Significantly Outdated Content',
                "This entry was last updated {$diffDays} days ago (over " . round($diffDays / 30) . " months).",
                'Review and update facts, figures, and internal links to ensure accuracy and relevance.',
                ['daysSinceUpdate' => $diffDays, 'thresholdDays' => $staleDays]
            );
        }

        if ($diffDays > $staleDays) {
            return $this->createResult(
                AuditResult::SEVERITY_NOTICE,
                'Content Due for Freshness Review',
                "Content has not been updated in {$diffDays} days.",
                'Check if any products, dates, or specifications mentioned need a refresh.',
                ['daysSinceUpdate' => $diffDays, 'thresholdDays' => $staleDays]
            );
        }

        return $this->createResult(
            AuditResult::SEVERITY_GOOD,
            'Recently Updated',
            "Content was refreshed within the last {$diffDays} days.",
            'No freshness action needed.',
            ['daysSinceUpdate' => $diffDays]
        );
    }
}
