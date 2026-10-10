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
     * Get semantically related Entry elements for frontend recommendations (Plus Edition).
     *
     * @param int $entryId
     * @param int $limit
     * @param int|null $siteId
     * @return Entry[]
     */
    public function getRelatedEntries(int $entryId, int $limit = 4, ?int $siteId = null): array
    {
        $plugin = Plugin::getInstance();
        if (!$plugin->hasPlus()) {
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
     * Perform frontend Hybrid Search across site knowledge base (Plus Edition).
     */
    public function search(string $query, int $limit = 5, ?int $siteId = null): array
    {
        $plugin = Plugin::getInstance();
        if (!$plugin->hasPlus()) {
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
        $vectorStats = $plugin->hasPlus() ? $plugin->vector->getIndexStats($siteId) : [];

        return [
            'audit' => $auditStats,
            'semantic' => $vectorStats,
        ];
    }
}
