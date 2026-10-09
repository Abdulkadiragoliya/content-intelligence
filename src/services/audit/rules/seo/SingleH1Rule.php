<?php

namespace abdulkadiragoliya\contentintelligence\services\audit\rules\seo;

use craft\elements\Entry;
use abdulkadiragoliya\contentintelligence\models\AuditResult;
use abdulkadiragoliya\contentintelligence\services\audit\BaseSeoAuditRule;

/**
 * Ensures optimal primary heading usage for SEO.
 */
class SingleH1Rule extends BaseSeoAuditRule
{
    public function getId(): string
    {
        return 'single_h1';
    }

    public function getName(): string
    {
        return 'Primary H1 Heading Hierarchy';
    }

    public function run(Entry $entry): ?AuditResult
    {
        $html = $this->extractRawHtml($entry);

        if (empty($html)) {
            return null;
        }

        preg_match_all('/<h1[^>]*>(.*?)<\/h1>/is', $html, $matches);
        $count = count($matches[0] ?? []);

        if ($count > 1) {
            return $this->createResult(
                AuditResult::SEVERITY_WARNING,
                'Multiple H1 Tags in Content Body',
                "Found {$count} <h1> tags inside custom fields. Having multiple H1 tags dilutes the topical focus for search crawlers.",
                'Convert secondary <h1> headings into <h2> subheadings and let the page template render a single main <h1> from the entry title.',
                ['count' => $count]
            );
        }

        return $this->createResult(
            AuditResult::SEVERITY_GOOD,
            'Clean H1 Structure',
            'No competing H1 tags detected inside content fields.',
            'Hierarchy is clean.'
        );
    }
}
