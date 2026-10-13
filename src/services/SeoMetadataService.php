<?php

namespace abdulkadiragoliya\contentintelligence\services;

use Craft;
use craft\base\Element;
use craft\elements\Entry;
use yii\base\Component;
use abdulkadiragoliya\contentintelligence\Plugin;
use abdulkadiragoliya\contentintelligence\records\SeoMetadataRecord;

/**
 * Service managing standalone SEO metadata storage, retrieval, and frontend HTML output.
 */
class SeoMetadataService extends Component
{
    /**
     * @var array<string, bool> Tracks rendered element/site pairs in the current request to prevent duplicates.
     */
    private static array $_rendered = [];

    /**
     * Retrieve the persistent SEO metadata record for an element on a specific site.
     *
     * @param int|null $elementId
     * @param int|null $siteId
     * @return SeoMetadataRecord|null
     */
    public function getMetadata(?int $elementId = null, ?int $siteId = null): ?SeoMetadataRecord
    {
        if (!$elementId) {
            return null;
        }

        try {
            $siteId = $siteId ?? Craft::$app->getSites()->getCurrentSite()->id;

            return SeoMetadataRecord::find()
                ->where(['elementId' => $elementId, 'siteId' => $siteId])
                ->one();
        } catch (\Throwable $e) {
            Craft::warning("Unable to fetch SEO metadata for element {$elementId}: " . $e->getMessage(), __METHOD__);
            return null;
        }
    }

    /**
     * Save approved SEO title and meta description for an element and site.
     *
     * @param int $elementId
     * @param int $siteId
     * @param string|null $seoTitle
     * @param string|null $metaDescription
     * @param bool $isAiGenerated
     * @return SeoMetadataRecord
     * @throws \yii\base\Exception
     */
    public function saveMetadata(
        int $elementId,
        int $siteId,
        ?string $seoTitle,
        ?string $metaDescription,
        bool $isAiGenerated = false
    ): SeoMetadataRecord {
        $cleanTitle = $seoTitle !== null ? trim($seoTitle) : null;
        $cleanDescription = $metaDescription !== null ? trim($metaDescription) : null;

        $record = SeoMetadataRecord::findOne([
            'elementId' => $elementId,
            'siteId' => $siteId,
        ]);

        if (!$record) {
            $record = new SeoMetadataRecord();
            $record->elementId = $elementId;
            $record->siteId = $siteId;
        }

        $record->seoTitle = !empty($cleanTitle) ? mb_substr($cleanTitle, 0, 255) : null;
        $record->metaDescription = !empty($cleanDescription) ? $cleanDescription : null;
        $record->isAiGenerated = $isAiGenerated;

        if (!$record->save()) {
            $errors = json_encode($record->getErrors());
            throw new \RuntimeException("Failed to save SEO metadata: {$errors}");
        }

        return $record;
    }

    /**
     * Delete SEO metadata for an element.
     *
     * @param int $elementId
     * @param int|null $siteId If null, deletes across all sites for this element.
     * @return bool
     */
    public function deleteMetadata(int $elementId, ?int $siteId = null): bool
    {
        $condition = ['elementId' => $elementId];
        if ($siteId !== null) {
            $condition['siteId'] = $siteId;
        }

        return (bool)SeoMetadataRecord::deleteAll($condition);
    }

    /**
     * Resolves the element instance from various inputs (Element, ID, or context).
     */
    public function resolveElement(mixed $element = null, ?int $siteId = null): ?Element
    {
        if ($element instanceof Element) {
            return $element;
        }

        $siteId = $siteId ?? Craft::$app->getSites()->getCurrentSite()->id;

        if (is_numeric($element) && (int)$element > 0) {
            return Craft::$app->getElements()->getElementById((int)$element, null, $siteId);
        }

        // Try resolving matched element from Craft URL manager (public frontend request)
        try {
            $matched = Craft::$app->getUrlManager()->getMatchedElement();
            if ($matched instanceof Element) {
                return $matched;
            }
        } catch (\Throwable) {
            // URL manager not available in console or test contexts
        }

        return null;
    }

    /**
     * Resolves structured SEO metadata for an element, applying fallbacks when values are unset.
     *
     * @param Element|int|null $element
     * @param int|null $siteId
     * @param array $options
     * @return array
     */
    public function resolveMetadata(mixed $element = null, ?int $siteId = null, array $options = []): array
    {
        $resolvedElement = $this->resolveElement($element, $siteId);
        $siteId = $siteId ?? ($resolvedElement?->siteId ?? Craft::$app->getSites()->getCurrentSite()->id);

        $record = null;
        if ($resolvedElement && $resolvedElement->id) {
            // In Craft, check the canonical element ID for drafts/revisions if applicable
            $lookupId = $resolvedElement->canonicalId ?: $resolvedElement->id;
            $record = $this->getMetadata($lookupId, $siteId);
        }

        $savedTitle = $record?->seoTitle ? trim($record->seoTitle) : '';
        $savedDescription = $record?->metaDescription ? trim($record->metaDescription) : '';

        // Title resolution and fallback
        $site = Craft::$app->getSites()->getSiteById($siteId) ?? Craft::$app->getSites()->getCurrentSite();
        $siteName = $site ? (string)$site->name : '';

        $title = $savedTitle;
        if (empty($title)) {
            if (!empty($options['fallbackTitle'])) {
                $title = trim((string)$options['fallbackTitle']);
            } elseif ($resolvedElement && !empty($resolvedElement->title)) {
                $title = trim((string)$resolvedElement->title);
                $siteSuffix = !empty($options['siteSuffix']) ? (string)$options['siteSuffix'] : " | {$siteName}";
                if (!empty($siteName) && !str_ends_with($title, $siteName)) {
                    $title .= $siteSuffix;
                }
            }
        }

        // Canonical URL
        $canonicalUrl = '';
        if (!empty($options['canonicalUrl'])) {
            $canonicalUrl = trim((string)$options['canonicalUrl']);
        } elseif ($resolvedElement && method_exists($resolvedElement, 'getUrl')) {
            $canonicalUrl = (string)$resolvedElement->getUrl();
        }

        // Optional image resolution (options override or element asset inspection)
        $ogImage = !empty($options['ogImage']) ? trim((string)$options['ogImage']) : '';
        if (empty($ogImage) && $resolvedElement) {
            foreach (['seoImage', 'ogImage', 'featuredImage', 'featureImage', 'image'] as $fieldHandle) {
                try {
                    if (isset($resolvedElement->$fieldHandle)) {
                        $val = $resolvedElement->$fieldHandle;
                        if ($val instanceof \craft\elements\db\AssetQuery) {
                            $asset = $val->one();
                            if ($asset && method_exists($asset, 'getUrl')) {
                                $ogImage = (string)$asset->getUrl();
                                break;
                            }
                        }
                    }
                } catch (\Throwable) {
                    // Ignore missing field exceptions
                }
            }
        }

        $robots = !empty($options['robots']) ? trim((string)$options['robots']) : '';
        $ogType = !empty($options['ogType']) ? trim((string)$options['ogType']) : 'website';

        return [
            'elementId' => $resolvedElement?->id,
            'siteId' => $siteId,
            'siteName' => $siteName,
            'seoTitle' => $savedTitle ?: null,
            'metaDescription' => $savedDescription ?: null,
            'renderedTitle' => $title,
            'renderedDescription' => $description,
            'canonicalUrl' => $canonicalUrl ?: null,
            'ogImage' => $ogImage ?: null,
            'ogType' => $ogType,
            'robots' => $robots ?: null,
            'isCustom' => !empty($savedTitle) || !empty($savedDescription),
            'isAiGenerated' => (bool)($record?->isAiGenerated ?? false),
            'dateUpdated' => $record?->dateUpdated ?? null,
        ];
    }

    /**
     * Renders standard standalone HTML SEO <title>, <meta name="description">, <link rel="canonical">,
     * OpenGraph tags, and Twitter Cards.
     * Prevents duplicate tag output within the same request and properly escapes HTML attributes.
     *
     * @param mixed $element
     * @param int|null $siteId
     * @param array $options Configuration options:
     *   - 'canonical' (bool): Toggle canonical link rendering (defaults to plugin setting)
     *   - 'og' / 'openGraph' (bool): Toggle OpenGraph tags rendering (defaults to plugin setting)
     *   - 'twitter' (bool): Toggle Twitter card tags rendering (defaults to plugin setting)
     *   - 'twitterCard' (string): Twitter card type ('summary_large_image' or 'summary')
     *   - 'canonicalUrl' (string): Custom canonical URL override
     *   - 'ogImage' (string): Custom social share image URL
     *   - 'robots' (string): Custom robots directives (e.g. 'noindex, follow')
     *   - 'force' (bool): Force re-rendering even if already rendered this request
     *   - 'fallbackTitle' (string): Custom fallback title
     *   - 'fallbackDescription' (string): Custom fallback description
     *   - 'siteSuffix' (string): Custom site suffix (e.g. " - My Brand")
     * @return string Valid, escaped HTML tags or empty string
     */
    public function renderHtml(mixed $element = null, ?int $siteId = null, array $options = []): string
    {
        $resolvedElement = $this->resolveElement($element, $siteId);
        $siteId = $siteId ?? ($resolvedElement?->siteId ?? Craft::$app->getSites()->getCurrentSite()->id);

        $renderKey = ($resolvedElement ? (string)$resolvedElement->id : 'global') . ':' . (string)$siteId;

        // Prevent duplicate injection within the same HTTP request unless forced
        if (!empty(self::$_rendered[$renderKey]) && empty($options['force'])) {
            return '';
        }

        $meta = $this->resolveMetadata($resolvedElement, $siteId, $options);
        $title = $meta['renderedTitle'];
        $description = $meta['renderedDescription'];
        $canonicalUrl = $meta['canonicalUrl'];
        $siteName = $meta['siteName'];
        $ogImage = $meta['ogImage'];

        if (empty($title) && empty($description) && empty($canonicalUrl)) {
            return '';
        }

        $settings = \abdulkadiragoliya\contentintelligence\Plugin::getInstance()?->getSettings();
        $shouldCanonical = isset($options['canonical']) ? (bool)$options['canonical'] : (bool)($settings?->getForSite('renderCanonical', $siteId) ?? true);
        $shouldOg = isset($options['og']) ? (bool)$options['og'] : (isset($options['openGraph']) ? (bool)$options['openGraph'] : (bool)($settings?->getForSite('renderOpenGraph', $siteId) ?? true));
        $shouldTwitter = isset($options['twitter']) ? (bool)$options['twitter'] : (bool)($settings?->getForSite('renderTwitterCard', $siteId) ?? true);
        $twitterCard = !empty($options['twitterCard']) ? (string)$options['twitterCard'] : (string)($settings?->getForSite('twitterCardType', $siteId) ?? 'summary_large_image');

        $htmlParts = [];

        // <title>
        if (!empty($title)) {
            $escapedTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
            $htmlParts[] = "<title>{$escapedTitle}</title>";
        }

        // <meta name="description">
        if (!empty($description)) {
            $escapedDesc = htmlspecialchars($description, ENT_QUOTES, 'UTF-8');
            $htmlParts[] = "<meta name=\"description\" content=\"{$escapedDesc}\">";
        }

        // <link rel="canonical">
        if ($shouldCanonical && !empty($canonicalUrl)) {
            $escapedCanonical = htmlspecialchars($canonicalUrl, ENT_QUOTES, 'UTF-8');
            $htmlParts[] = "<link rel=\"canonical\" href=\"{$escapedCanonical}\">";
        }

        // OpenGraph
        if ($shouldOg) {
            if (!empty($title)) {
                $escapedTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
                $htmlParts[] = "<meta property=\"og:title\" content=\"{$escapedTitle}\">";
            }
            if (!empty($description)) {
                $escapedDesc = htmlspecialchars($description, ENT_QUOTES, 'UTF-8');
                $htmlParts[] = "<meta property=\"og:description\" content=\"{$escapedDesc}\">";
            }
            if (!empty($canonicalUrl)) {
                $escapedCanonical = htmlspecialchars($canonicalUrl, ENT_QUOTES, 'UTF-8');
                $htmlParts[] = "<meta property=\"og:url\" content=\"{$escapedCanonical}\">";
            }
            $ogType = htmlspecialchars($meta['ogType'] ?: 'website', ENT_QUOTES, 'UTF-8');
            $htmlParts[] = "<meta property=\"og:type\" content=\"{$ogType}\">";
            if (!empty($siteName)) {
                $escapedSiteName = htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8');
                $htmlParts[] = "<meta property=\"og:site_name\" content=\"{$escapedSiteName}\">";
            }
            if (!empty($ogImage)) {
                $escapedImg = htmlspecialchars($ogImage, ENT_QUOTES, 'UTF-8');
                $htmlParts[] = "<meta property=\"og:image\" content=\"{$escapedImg}\">";
            }
        }

        // Twitter Card
        if ($shouldTwitter) {
            $escapedCard = htmlspecialchars($twitterCard, ENT_QUOTES, 'UTF-8');
            $htmlParts[] = "<meta name=\"twitter:card\" content=\"{$escapedCard}\">";
            if (!empty($title)) {
                $escapedTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
                $htmlParts[] = "<meta name=\"twitter:title\" content=\"{$escapedTitle}\">";
            }
            if (!empty($description)) {
                $escapedDesc = htmlspecialchars($description, ENT_QUOTES, 'UTF-8');
                $htmlParts[] = "<meta name=\"twitter:description\" content=\"{$escapedDesc}\">";
            }
            if (!empty($ogImage)) {
                $escapedImg = htmlspecialchars($ogImage, ENT_QUOTES, 'UTF-8');
                $htmlParts[] = "<meta name=\"twitter:image\" content=\"{$escapedImg}\">";
            }
        }

        // Robots
        if (!empty($meta['robots'])) {
            $escapedRobots = htmlspecialchars($meta['robots'], ENT_QUOTES, 'UTF-8');
            $htmlParts[] = "<meta name=\"robots\" content=\"{$escapedRobots}\">";
        }

        self::$_rendered[$renderKey] = true;

        return implode("\n", $htmlParts);
    }

    /**
     * Resets internal deduplication cache (useful for automated tests and sub-requests).
     */
    public static function resetRenderedCache(): void
    {
        self::$_rendered = [];
    }
}
