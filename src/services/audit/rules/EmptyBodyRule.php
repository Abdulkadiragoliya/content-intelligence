<?php

namespace abdulkadiragoliya\contentintelligence\services\audit\rules;

use craft\elements\Entry;
use abdulkadiragoliya\contentintelligence\models\AuditResult;
use abdulkadiragoliya\contentintelligence\services\audit\BaseAuditRule;

/**
 * Checks for empty body content where fields exist.
 */
class EmptyBodyRule extends BaseAuditRule
{
    public function getId(): string
    {
        return 'empty_body';
    }

    public function getName(): string
    {
        return 'Empty Content Fields';
    }

    public function run(Entry $entry): ?AuditResult
    {
        $fieldLayout = $entry->getFieldLayout();
        if (!$fieldLayout) {
            return null;
        }

        $customFields = $fieldLayout->getCustomFields();
        if (empty($customFields)) {
            return null;
        }

        $hasAnyContent = false;
        foreach ($customFields as $field) {
            $val = $entry->getFieldValue($field->handle);
            if (!empty($val)) {
                $hasAnyContent = true;
                break;
            }
        }

        if (!$hasAnyContent) {
            return $this->createResult(
                AuditResult::SEVERITY_CRITICAL,
                'Empty Entry Content',
                'All custom content fields for this entry are blank.',
                'Fill in the content fields for this entry or disable it until it is ready.'
            );
        }

        return null;
    }
}
