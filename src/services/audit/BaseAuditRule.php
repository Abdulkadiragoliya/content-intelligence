<?php

namespace abdulkadiragoliya\contentintelligence\services\audit;

use craft\base\Element;
use craft\elements\Entry;
use craft\elements\MatrixBlock;
use abdulkadiragoliya\contentintelligence\models\AuditResult;

/**
 * Base abstract class for Audit Rules providing shared content inspection utilities.
 */
abstract class BaseAuditRule implements AuditRuleInterface
{
    /**
     * @inheritdoc
     */
    public function getCategory(): string
    {
        return 'content';
    }

    /**
     * Extracts normalized plaintext representation of all body and matrix content of an entry.
     */
    protected function extractText(Entry $entry): string
    {
        $textParts = [];

        // Add Entry Title
        if (!empty($entry->title)) {
            $textParts[] = (string)$entry->title;
        }

        // Loop through Custom Fields on Entry Layout
        $fieldLayout = $entry->getFieldLayout();
        if ($fieldLayout) {
            foreach ($fieldLayout->getCustomFields() as $field) {
                $value = $entry->getFieldValue($field->handle);
                $textParts[] = $this->extractValueText($value);
            }
        }

        return trim(implode("\n\n", array_filter($textParts)));
    }

    /**
     * Recursively extracts text from custom field values including Matrix & nested elements.
     */
    protected function extractValueText(mixed $value): string
    {
        if (empty($value)) {
            return '';
        }

        if (is_string($value)) {
            // Strip HTML tags and normalize spacing
            return trim(html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }

        if (is_numeric($value)) {
            return (string)$value;
        }

        // Handle Array or Collection values
        if (is_iterable($value)) {
            $parts = [];
            foreach ($value as $item) {
                if ($item instanceof MatrixBlock || $item instanceof Element) {
                    $itemLayout = $item->getFieldLayout();
                    if ($itemLayout) {
                        foreach ($itemLayout->getCustomFields() as $subField) {
                            $subValue = $item->getFieldValue($subField->handle);
                            $parts[] = $this->extractValueText($subValue);
                        }
                    }
                } else {
                    $parts[] = $this->extractValueText($item);
                }
            }
            return implode(' ', array_filter($parts));
        }

        return '';
    }

    /**
     * Extracts all raw HTML content from an entry for structural/tag audits.
     */
    protected function extractRawHtml(Entry $entry): string
    {
        $htmlParts = [];
        $fieldLayout = $entry->getFieldLayout();
        if ($fieldLayout) {
            foreach ($fieldLayout->getCustomFields() as $field) {
                $val = $entry->getFieldValue($field->handle);
                if (is_string($val) && (str_contains($val, '<') || str_contains($val, '>'))) {
                    $htmlParts[] = $val;
                }
            }
        }
        return implode("\n", $htmlParts);
    }

    /**
     * Count words in plain text string accurately.
     */
    protected function countWords(string $text): int
    {
        if (empty($text)) {
            return 0;
        }
        return str_word_count(strip_tags($text));
    }

    /**
     * Helper to construct a standardized AuditResult.
     */
    protected function createResult(
        string $severity,
        string $title,
        string $description,
        string $recommendation,
        array $context = []
    ): AuditResult {
        return new AuditResult([
            'ruleId' => $this->getId(),
            'category' => $this->getCategory(),
            'severity' => $severity,
            'title' => $title,
            'description' => $description,
            'recommendation' => $recommendation,
            'context' => $context,
        ]);
    }
}
