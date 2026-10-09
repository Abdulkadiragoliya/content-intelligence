<?php

namespace abdulkadiragoliya\contentintelligence\services\audit\rules;

use craft\elements\Entry;
use abdulkadiragoliya\contentintelligence\models\AuditResult;
use abdulkadiragoliya\contentintelligence\services\audit\BaseAuditRule;

/**
 * Checks for missing or placeholder entry titles.
 */
class MissingTitleRule extends BaseAuditRule
{
    public function getId(): string
    {
        return 'missing_title';
    }

    public function getName(): string
    {
        return 'Entry Title Completeness';
    }

    public function run(Entry $entry): ?AuditResult
    {
        $title = trim((string)$entry->title);

        if (empty($title)) {
            return $this->createResult(
                AuditResult::SEVERITY_CRITICAL,
                'Missing Title',
                'This entry does not have a title defined.',
                'Provide a descriptive title accurately summarizing the content.'
            );
        }

        $placeholders = ['untitled', 'untitled entry', 'new entry', 'temp', 'test'];
        if (in_array(strtolower($title), $placeholders, true)) {
            return $this->createResult(
                AuditResult::SEVERITY_WARNING,
                'Generic Placeholder Title',
                "The title '{$title}' appears to be an unedited placeholder.",
                'Replace placeholder titles with meaningful, topic-focused names.'
            );
        }

        return $this->createResult(
            AuditResult::SEVERITY_GOOD,
            'Title Present',
            'The entry has a valid descriptive title.',
            'No action needed.'
        );
    }
}
