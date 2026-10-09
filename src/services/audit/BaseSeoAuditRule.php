<?php

namespace abdulkadiragoliya\contentintelligence\services\audit;

use craft\elements\Entry;
use craft\elements\Asset;
use abdulkadiragoliya\contentintelligence\services\audit\BaseAuditRule;

/**
 * Base class for SEO-specific audit rules.
 */
abstract class BaseSeoAuditRule extends BaseAuditRule
{
    /**
     * @inheritdoc
     */
    public function getCategory(): string
    {
        return 'seo';
    }

    /**
     * Attempts to resolve the SEO title for an entry.
     * Checks common SEO field handles, falling back to entry title.
     */
    protected function resolveMetaTitle(Entry $entry): string
    {
        $candidateHandles = ['seoTitle', 'metaTitle', 'titleTag', 'customTitle', 'ogTitle'];

        foreach ($candidateHandles as $handle) {
            if ($entry->hasProperty($handle) || $entry->getBehavior($handle) !== null) {
                $val = (string)$entry->getFieldValue($handle);
                if (!empty(trim($val))) {
                    return trim($val);
                }
            }
        }

        return trim((string)$entry->title);
    }

    /**
     * Attempts to resolve the SEO description for an entry.
     * Checks common SEO field handles, summary, or first paragraph excerpt.
     */
    protected function resolveMetaDescription(Entry $entry): string
    {
        $candidateHandles = ['seoDescription', 'metaDescription', 'description', 'summary', 'excerpt', 'ogDescription'];

        foreach ($candidateHandles as $handle) {
            $field = $entry->getFieldLayout()?->getFieldByHandle($handle);
            if ($field) {
                $val = (string)$entry->getFieldValue($handle);
                if (!empty(trim($val))) {
                    return trim(strip_tags($val));
                }
            }
        }

        // Fallback: extract first substantial sentence/paragraph from body text
        $body = $this->extractText($entry);
        if (!empty($body)) {
            $cleaned = preg_replace('/\s+/', ' ', $body);
            return mb_substr($cleaned, 0, 160);
        }

        return '';
    }

    /**
     * Extracts all <img> tags from entry HTML fields along with their attributes.
     *
     * @param Entry $entry
     * @return array Array of ['src' => ..., 'alt' => ..., 'raw' => ...]
     */
    protected function extractImages(Entry $entry): array
    {
        $html = $this->extractRawHtml($entry);
        $images = [];

        if (!empty($html)) {
            preg_match_all('/<img\s+([^>]*?)>/is', $html, $matches);
            foreach ($matches[1] as $index => $attrString) {
                $src = '';
                $alt = null;

                if (preg_match('/src=["\']([^"\']*)["\']/i', $attrString, $srcMatch)) {
                    $src = $srcMatch[1];
                }

                if (preg_match('/alt=["\']([^"\']*)["\']/i', $attrString, $altMatch)) {
                    $alt = $altMatch[1];
                }

                $images[] = [
                    'src' => $src,
                    'alt' => $alt, // null if alt attribute missing entirely
                    'raw' => $matches[0][$index] ?? '',
                ];
            }
        }

        // Also check any attached Asset custom fields
        $fieldLayout = $entry->getFieldLayout();
        if ($fieldLayout) {
            foreach ($fieldLayout->getCustomFields() as $field) {
                $val = $entry->getFieldValue($field->handle);
                if ($val instanceof \craft\elements\db\AssetQuery) {
                    foreach ($val->all() as $asset) {
                        /** @var Asset $asset */
                        if ($asset->kind === Asset::KIND_IMAGE) {
                            $images[] = [
                                'src' => $asset->filename,
                                'alt' => $asset->alt,
                                'title' => $asset->title,
                                'assetId' => $asset->id,
                                'isAsset' => true,
                            ];
                        }
                    }
                }
            }
        }

        return $images;
    }

    /**
     * Extracts all links (<a href="...">) and categorizes internal vs external.
     */
    protected function extractLinks(Entry $entry): array
    {
        $html = $this->extractRawHtml($entry);
        $links = [];

        if (empty($html)) {
            return $links;
        }

        preg_match_all('/<a\s+[^>]*href=["\']([^"\']*)["\'][^>]*>(.*?)<\/a>/is', $html, $matches);
        $siteUrl = $entry->getSite()->baseUrl ?? '';

        foreach ($matches[1] as $idx => $href) {
            $anchorText = trim(strip_tags($matches[2][$idx] ?? ''));
            $isInternal = str_starts_with($href, '/') || 
                          str_starts_with($href, '#') || 
                          (!empty($siteUrl) && str_starts_with($href, $siteUrl));

            $links[] = [
                'href' => $href,
                'text' => $anchorText,
                'isInternal' => $isInternal,
            ];
        }

        return $links;
    }
}
