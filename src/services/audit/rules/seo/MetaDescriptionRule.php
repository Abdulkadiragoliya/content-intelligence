<?php

namespace abdulkadiragoliya\contentintelligence\services\audit\rules\seo;

use craft\elements\Entry;
use abdulkadiragoliya\contentintelligence\Plugin;
use abdulkadiragoliya\contentintelligence\models\AuditResult;
use abdulkadiragoliya\contentintelligence\services\audit\BaseSeoAuditRule;

/**
 * Evaluates SEO meta description presence, depth, and character length.
 */
class MetaDescriptionRule extends BaseSeoAuditRule
{
    public function getId(): string
    {
        return 'meta_description';
    }

    public function getName(): string
    {
        return 'Meta Description Optimization';
    }

    public function run(Entry $entry): ?AuditResult
    {
        $settings = Plugin::getInstance()->getSettings();
        $minLen = $settings->minDescriptionLength ?? 70;
        $maxLen = $settings->maxDescriptionLength ?? 160;

        $description = $this->resolveMetaDescription($entry);
        $length = mb_strlen($description);

        if (empty($description)) {
            return $this->createResult(
                AuditResult::SEVERITY_CRITICAL,
                'Missing Meta Description',
                'This entry has no meta description defined. Search engines will generate random snippets.',
                "Write a compelling meta description between {$minLen} and {$maxLen} characters.",
                ['length' => 0, 'min' => $minLen, 'max' => $maxLen]
            );
        }

        if ($length < $minLen) {
            return $this->createResult(
                AuditResult::SEVERITY_WARNING,
                'Meta Description Too Short',
                "The description is only {$length} characters long, which is under the recommended {$minLen} characters.",
                "Expand the meta description to {$minLen}–{$maxLen} characters to increase search snippet click-through rates.",
                ['description' => $description, 'length' => $length, 'min' => $minLen, 'max' => $maxLen]
            );
        }

        if ($length > $maxLen) {
            return $this->createResult(
                AuditResult::SEVERITY_WARNING,
                'Meta Description Exceeds Snippet Length',
                "The description is {$length} characters long and will be truncated by search engines (max {$maxLen} characters).",
                "Trim the description to {$maxLen} characters or fewer while keeping the core call-to-action intact.",
                ['description' => $description, 'length' => $length, 'min' => $minLen, 'max' => $maxLen]
            );
        }

        return $this->createResult(
            AuditResult::SEVERITY_GOOD,
            'Optimal Meta Description Length',
            "Meta description is well-balanced ({$length} characters) within the optimal {$minLen}–{$maxLen} character range.",
            'No changes needed.',
            ['description' => $description, 'length' => $length]
        );
    }
}
