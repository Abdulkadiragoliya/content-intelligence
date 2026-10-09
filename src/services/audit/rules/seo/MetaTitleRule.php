<?php

namespace abdulkadiragoliya\contentintelligence\services\audit\rules\seo;

use craft\elements\Entry;
use abdulkadiragoliya\contentintelligence\Plugin;
use abdulkadiragoliya\contentintelligence\models\AuditResult;
use abdulkadiragoliya\contentintelligence\services\audit\BaseSeoAuditRule;

/**
 * Evaluates SEO meta title presence and character length.
 */
class MetaTitleRule extends BaseSeoAuditRule
{
    public function getId(): string
    {
        return 'meta_title';
    }

    public function getName(): string
    {
        return 'Meta Title Optimization';
    }

    public function run(Entry $entry): ?AuditResult
    {
        $settings = Plugin::getInstance()->getSettings();
        $minLen = $settings->minTitleLength ?? 30;
        $maxLen = $settings->maxTitleLength ?? 60;

        $title = $this->resolveMetaTitle($entry);
        $length = mb_strlen($title);

        if (empty($title)) {
            return $this->createResult(
                AuditResult::SEVERITY_CRITICAL,
                'Missing SEO Meta Title',
                'This entry does not have an SEO title defined.',
                "Add a concise meta title between {$minLen} and {$maxLen} characters.",
                ['length' => 0, 'min' => $minLen, 'max' => $maxLen]
            );
        }

        if ($length < $minLen) {
            return $this->createResult(
                AuditResult::SEVERITY_WARNING,
                'SEO Title Too Short',
                "The title '{$title}' is {$length} characters, which is under the recommended {$minLen} characters.",
                "Expand the title to {$minLen}–{$maxLen} characters to include targeted keywords or brand context.",
                ['title' => $title, 'length' => $length, 'min' => $minLen, 'max' => $maxLen]
            );
        }

        if ($length > $maxLen) {
            return $this->createResult(
                AuditResult::SEVERITY_WARNING,
                'SEO Title Exceeds Snippet Length',
                "The title is {$length} characters long, exceeding search engine display limits of {$maxLen} characters.",
                "Shorten the title to {$maxLen} characters or fewer to prevent truncation in search result snippets.",
                ['title' => $title, 'length' => $length, 'min' => $minLen, 'max' => $maxLen]
            );
        }

        return $this->createResult(
            AuditResult::SEVERITY_GOOD,
            'Optimal SEO Title Length',
            "Title length ({$length} characters) is within the optimal range of {$minLen}–{$maxLen} characters.",
            'No changes needed.',
            ['title' => $title, 'length' => $length]
        );
    }
}
