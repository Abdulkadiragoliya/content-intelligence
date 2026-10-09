<?php

namespace abdulkadiragoliya\contentintelligence\services\audit\rules\seo;

use craft\elements\Entry;
use abdulkadiragoliya\contentintelligence\models\AuditResult;
use abdulkadiragoliya\contentintelligence\services\audit\BaseSeoAuditRule;

/**
 * Checks image alt-text accessibility and SEO descriptive tags.
 */
class ImageAltTextRule extends BaseSeoAuditRule
{
    public function getId(): string
    {
        return 'image_alt_text';
    }

    public function getName(): string
    {
        return 'Image Alt-Text Accessibility & SEO';
    }

    public function run(Entry $entry): ?AuditResult
    {
        $images = $this->extractImages($entry);

        if (empty($images)) {
            return null; // No images in this entry
        }

        $totalImages = count($images);
        $missingAlt = 0;
        $filenameAlt = 0;

        foreach ($images as $img) {
            $alt = $img['alt'];
            if ($alt === null || trim($alt) === '') {
                $missingAlt++;
            } else {
                // Check if alt text looks like a raw filename (e.g. image.jpg, IMG_123.png)
                if (preg_match('/\.(jpe?g|png|webp|gif|svg)$/i', $alt) || preg_match('/^(img|dsc|screenshot|photo)[_\-0-9]+/i', $alt)) {
                    $filenameAlt++;
                }
            }
        }

        if ($missingAlt === $totalImages) {
            return $this->createResult(
                AuditResult::SEVERITY_CRITICAL,
                'All Images Missing Alt Text',
                "None of the {$totalImages} image(s) on this entry have descriptive alt text defined.",
                'Add descriptive alt text for each image to comply with accessibility standards and improve image search rankings.',
                ['totalImages' => $totalImages, 'missingAlt' => $missingAlt]
            );
        }

        if ($missingAlt > 0 || $filenameAlt > 0) {
            $issues = [];
            if ($missingAlt > 0) $issues[] = "{$missingAlt} missing alt text";
            if ($filenameAlt > 0) $issues[] = "{$filenameAlt} using raw filenames";

            return $this->createResult(
                AuditResult::SEVERITY_WARNING,
                'Incomplete Image Alt Text',
                "Found image accessibility issues: " . implode(', ', $issues) . " out of {$totalImages} total image(s).",
                'Ensure all non-decorative images feature descriptive, contextual alt text.',
                ['totalImages' => $totalImages, 'missingAlt' => $missingAlt, 'filenameAlt' => $filenameAlt]
            );
        }

        return $this->createResult(
            AuditResult::SEVERITY_GOOD,
            'All Images Have Alt Text',
            "All {$totalImages} image(s) have descriptive alt text defined.",
            'No image accessibility issues detected.',
            ['totalImages' => $totalImages]
        );
    }
}
