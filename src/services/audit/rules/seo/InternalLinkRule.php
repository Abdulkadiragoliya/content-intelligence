<?php

namespace abdulkadiragoliya\contentintelligence\services\audit\rules\seo;

use craft\elements\Entry;
use abdulkadiragoliya\contentintelligence\models\AuditResult;
use abdulkadiragoliya\contentintelligence\services\audit\BaseSeoAuditRule;

/**
 * Checks internal link density, presence, and anchor text quality.
 */
class InternalLinkRule extends BaseSeoAuditRule
{
    public function getId(): string
    {
        return 'internal_links';
    }

    public function getName(): string
    {
        return 'Internal Linking Opportunities';
    }

    public function run(Entry $entry): ?AuditResult
    {
        $links = $this->extractLinks($entry);
        $totalLinks = count($links);

        if ($totalLinks === 0) {
            return $this->createResult(
                AuditResult::SEVERITY_NOTICE,
                'Zero Links in Content',
                'This entry does not contain any links to other pages or external resources.',
                'Add relevant internal links to related website pages to distribute authority and improve reader navigation.'
            );
        }

        $internalLinks = array_filter($links, fn($l) => $l['isInternal']);
        $internalCount = count($internalLinks);

        if ($internalCount === 0) {
            return $this->createResult(
                AuditResult::SEVERITY_WARNING,
                'No Internal Links Found',
                'This entry links to external sites but does not contain any links to other pages within your own website.',
                'Add 2–3 links to related articles, categories, or services to build strong internal site architecture.',
                ['totalLinks' => $totalLinks, 'internalCount' => 0]
            );
        }

        // Check for generic anchor text
        $genericAnchors = ['click here', 'read more', 'learn more', 'here', 'link', 'more'];
        $genericCount = 0;
        foreach ($links as $l) {
            if (in_array(strtolower($l['text']), $genericAnchors, true)) {
                $genericCount++;
            }
        }

        if ($genericCount > 0) {
            return $this->createResult(
                AuditResult::SEVERITY_NOTICE,
                'Generic Anchor Text Detected',
                "Found {$genericCount} link(s) using non-descriptive anchor text like 'click here' or 'read more'.",
                'Use descriptive keyword-rich anchor text that clearly explains where the link leads.',
                ['genericCount' => $genericCount, 'internalCount' => $internalCount]
            );
        }

        return $this->createResult(
            AuditResult::SEVERITY_GOOD,
            'Healthy Internal Linking',
            "Found {$internalCount} internal link(s) with descriptive anchor text.",
            'No linking issues found.',
            ['internalCount' => $internalCount, 'totalLinks' => $totalLinks]
        );
    }
}
