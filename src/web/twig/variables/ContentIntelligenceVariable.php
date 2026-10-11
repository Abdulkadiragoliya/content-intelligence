<?php

namespace abdulkadiragoliya\contentintelligence\web\twig\variables;

use Craft;
use craft\elements\Entry;
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
}
