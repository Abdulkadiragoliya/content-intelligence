<?php

namespace abdulkadiragoliya\contentintelligence\services\audit\rules;

use craft\elements\Entry;
use abdulkadiragoliya\contentintelligence\Plugin;
use abdulkadiragoliya\contentintelligence\models\AuditResult;
use abdulkadiragoliya\contentintelligence\services\audit\BaseAuditRule;

/**
 * Checks for thin or insubstantial body content.
 */
class ThinContentRule extends BaseAuditRule
{
    public function getId(): string
    {
        return 'thin_content';
    }

    public function getName(): string
    {
        return 'Content Depth & Word Count';
    }

    public function run(Entry $entry): ?AuditResult
    {
        $settings = Plugin::getInstance()->getSettings();
        $minWords = $settings->minContentWordCount ?? 300;

        $text = $this->extractText($entry);
        $wordCount = $this->countWords($text);

        if ($wordCount === 0) {
            return $this->createResult(
                AuditResult::SEVERITY_CRITICAL,
                'Empty Content',
                'This entry contains zero readable body words.',
                'Add substantive text content to provide value to site visitors.',
                ['wordCount' => 0, 'minWords' => $minWords]
            );
        }

        if ($wordCount < 60) {
            return $this->createResult(
                AuditResult::SEVERITY_CRITICAL,
                'Very Thin Content',
                "This entry contains only {$wordCount} words, which search engines and users consider very thin.",
                "Expand this entry with detailed information (aim for at least {$minWords} words).",
                ['wordCount' => $wordCount, 'minWords' => $minWords]
            );
        }

        if ($wordCount < $minWords) {
            return $this->createResult(
                AuditResult::SEVERITY_WARNING,
                'Thin Content Under Threshold',
                "Content length is {$wordCount} words, which is below your target threshold of {$minWords} words.",
                "Consider expanding this entry with further details, examples, or FAQs.",
                ['wordCount' => $wordCount, 'minWords' => $minWords]
            );
        }

        return $this->createResult(
            AuditResult::SEVERITY_GOOD,
            'Sufficient Content Depth',
            "Entry has a healthy length of {$wordCount} words.",
            'Content depth meets recommended guidelines.',
            ['wordCount' => $wordCount, 'minWords' => $minWords]
        );
    }
}
