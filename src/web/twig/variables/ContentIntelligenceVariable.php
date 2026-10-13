<?php

namespace abdulkadiragoliya\contentintelligence\web\twig\variables;

use Craft;
use craft\elements\Entry;
use craft\helpers\Template;
use Twig\Markup;
use abdulkadiragoliya\contentintelligence\Plugin;
use abdulkadiragoliya\contentintelligence\records\AuditRecord;

/**
 * Public Craft CMS template variable: craft.contentIntelligence
 * Exposes Content Intelligence services to frontend Twig templates.
 */
class ContentIntelligenceVariable
{
    /**
     * Get active plugin edition.
     */
    public function getEdition(): string
    {
        return Plugin::getInstance()->getActiveEdition();
    }

    /**
     * Get overall, content, and SEO scores for an entry.
     */
    public function getEntryScore(int $entryId, ?int $siteId = null): ?array
    {
        $siteId = $siteId ?? Craft::$app->getSites()->getCurrentSite()->id;
        $audit = Plugin::getInstance()->audit->getLatestAuditForEntry($entryId, $siteId);

        if (!$audit) {
            return null;
        }

        return [
            'overall' => (int)$audit->overallScore,
            'content' => (int)$audit->contentScore,
            'seo' => (int)$audit->seoScore,
            'status' => $audit->getStatus(),
            'dateAudited' => $audit->dateUpdated,
        ];
    }

    /**
     * Get the full AuditRecord for an entry.
     */
    public function getEntryAudit(int $entryId, ?int $siteId = null): ?AuditRecord
    {
        $siteId = $siteId ?? Craft::$app->getSites()->getCurrentSite()->id;
        return Plugin::getInstance()->audit->getLatestAuditForEntry($entryId, $siteId);
    }

    /**
     * Check if active edition has Plus tier features (Plus or Pro).
     */
    public function hasPlus(): bool
    {
        return Plugin::getInstance()->hasPlus();
    }

    /**
     * Check if active edition has Pro tier features.
     */
    public function hasPro(): bool
    {
        return Plugin::getInstance()->hasPro();
    }

    /**
     * Check if AI assistant is unlocked.
     */
    public function hasAi(): bool
    {
        return Plugin::getInstance()->hasAi();
    }

    /**
     * Check if vector DB is unlocked.
     */
    public function hasVector(): bool
    {
        return Plugin::getInstance()->hasVector();
    }

    /**
     * Check if RAG is unlocked.
     */
    public function hasRag(): bool
    {
        return Plugin::getInstance()->hasRag();
    }

    /**
     * Get semantically related Entry elements for frontend recommendations (Pro Edition).
     *
     * @param int $entryId
     * @param int $limit
     * @param int|null $siteId
     * @return Entry[]
     */
    public function getRelatedEntries(int $entryId, int $limit = 4, ?int $siteId = null): array
    {
        $plugin = Plugin::getInstance();
        if (!$plugin->hasPro()) {
            return [];
        }

        $siteId = $siteId ?? Craft::$app->getSites()->getCurrentSite()->id;
        $recs = $plugin->rag->getRecommendations($entryId, $siteId, $limit);

        if (empty($recs)) {
            return [];
        }

        $entryIds = array_column($recs, 'entryId');
        return Entry::find()
            ->id($entryIds)
            ->siteId($siteId)
            ->status('live')
            ->fixedOrder()
            ->all();
    }

    /**
     * Perform frontend Hybrid Search across site knowledge base (Pro Edition).
     */
    public function search(string $query, int $limit = 5, ?int $siteId = null): array
    {
        $plugin = Plugin::getInstance();
        if (!$plugin->hasPro()) {
            return [];
        }

        $siteId = $siteId ?? Craft::$app->getSites()->getCurrentSite()->id;
        return $plugin->vector->hybridSearch($query, $siteId, $limit);
    }

    /**
     * Get high-level audit & knowledge statistics for the current site.
     */
    public function getSiteStats(?int $siteId = null): array
    {
        $siteId = $siteId ?? Craft::$app->getSites()->getCurrentSite()->id;
        $plugin = Plugin::getInstance();

        $auditStats = $plugin->audit->getAuditStats($siteId);
        $vectorStats = $plugin->hasPro() ? $plugin->vector->getIndexStats($siteId) : [];

        return [
            'audit' => $auditStats,
            'semantic' => $vectorStats,
        ];
    }

    /**
     * Renders standalone HTML SEO <title> and <meta name="description"> tags for the website.
     *
     * Example Twig usage:
     *   {{ craft.contentIntelligence.renderSeoMeta(entry) }}
     *   {{ craft.contentIntelligence.renderSeoMeta() }}
     *
     * @param mixed $element Element, element ID, or null (defaults to matched element)
     * @param int|null $siteId Site ID or null (defaults to current site)
     * @param array $options Optional configuration ('fallbackTitle', 'fallbackDescription', 'siteSuffix', 'force')
     * @return string
     */
    public function renderSeoMeta(mixed $element = null, ?int $siteId = null, array $options = []): Markup
    {
        $html = Plugin::getInstance()->seoMetadata->renderHtml($element, $siteId, $options);
        return Template::raw($html);
    }

    /**
     * Resolves structured SEO metadata array for an element and site.
     *
     * Example Twig usage:
     *   {% set seo = craft.contentIntelligence.getSeo(entry) %}
     *   <title>{{ seo.renderedTitle }}</title>
     *
     * @param mixed $element
     * @param int|null $siteId
     * @param array $options
     * @return array
     */
    public function getSeo(mixed $element = null, ?int $siteId = null, array $options = []): array
    {
        return Plugin::getInstance()->seoMetadata->resolveMetadata($element, $siteId, $options);
    }

    /**
     * Returns the raw persistent SeoMetadataRecord for an element.
     *
     * @param mixed $element ElementInterface, element ID, or null
     * @param int|null $siteId
     * @return \abdulkadiragoliya\contentintelligence\records\SeoMetadataRecord|null
     */
    public function getMetadata(mixed $element = null, ?int $siteId = null): ?\abdulkadiragoliya\contentintelligence\records\SeoMetadataRecord
    {
        if ($element === null) {
            return null;
        }

        $elementId = $element instanceof \craft\base\ElementInterface
            ? (int)($element->canonicalId ?: $element->id)
            : (int)$element;

        if (!$elementId) {
            return null;
        }

        return Plugin::getInstance()->seoMetadata->getMetadata($elementId, $siteId);
    }
}
