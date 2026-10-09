<?php

namespace abdulkadiragoliya\contentintelligence\services;

use Craft;
use yii\base\Component;
use craft\elements\Entry;
use abdulkadiragoliya\contentintelligence\records\AuditRecord;
use abdulkadiragoliya\contentintelligence\records\AuditResultRecord;
use abdulkadiragoliya\contentintelligence\models\AuditResult;

/**
 * Service managing SEO Intelligence audits, metrics, and SERP previews.
 */
class SeoService extends Component
{
    /**
     * Get aggregate SEO statistics for a site.
     */
    public function getSeoStats(?int $siteId = null): array
    {
        $siteId = $siteId ?? Craft::$app->getSites()->getCurrentSite()->id;
        $validIds = Entry::find()->siteId($siteId)->section('*')->status(null)->ids();
        $totalEntries = count($validIds);

        $audits = empty($validIds) ? [] : AuditRecord::find()->where(['siteId' => $siteId, 'entryId' => $validIds])->all();
        $analyzedCount = count($audits);

        if ($analyzedCount === 0 || empty($validIds)) {
            return [
                'totalEntries' => $totalEntries,
                'analyzedCount' => 0,
                'avgSeoScore' => 100,
                'missingTitleCount' => 0,
                'missingDescriptionCount' => 0,
                'missingAltCount' => 0,
                'noInternalLinksCount' => 0,
            ];
        }

        $totalSeoScore = 0;
        foreach ($audits as $audit) {
            $totalSeoScore += $audit->seoScore;
        }

        // Count specific SEO violations from audit results table for section entries
        $missingTitleCount = (int)AuditResultRecord::find()
            ->where(['siteId' => $siteId, 'ruleId' => 'meta_title', 'severity' => AuditResult::SEVERITY_CRITICAL])
            ->andWhere(['in', 'entryId', $validIds])
            ->count();

        $missingDescriptionCount = (int)AuditResultRecord::find()
            ->where(['siteId' => $siteId, 'ruleId' => 'meta_description', 'severity' => AuditResult::SEVERITY_CRITICAL])
            ->andWhere(['in', 'entryId', $validIds])
            ->count();

        $missingAltCount = (int)AuditResultRecord::find()
            ->where(['siteId' => $siteId, 'ruleId' => 'image_alt_text'])
            ->andWhere(['in', 'severity', [AuditResult::SEVERITY_CRITICAL, AuditResult::SEVERITY_WARNING]])
            ->andWhere(['in', 'entryId', $validIds])
            ->count();

        $noInternalLinksCount = (int)AuditResultRecord::find()
            ->where(['siteId' => $siteId, 'ruleId' => 'internal_links'])
            ->andWhere(['in', 'severity', [AuditResult::SEVERITY_WARNING, AuditResult::SEVERITY_NOTICE]])
            ->andWhere(['in', 'entryId', $validIds])
            ->count();

        return [
            'totalEntries' => $totalEntries,
            'analyzedCount' => $analyzedCount,
            'avgSeoScore' => round($totalSeoScore / $analyzedCount),
            'missingTitleCount' => $missingTitleCount,
            'missingDescriptionCount' => $missingDescriptionCount,
            'missingAltCount' => $missingAltCount,
            'noInternalLinksCount' => $noInternalLinksCount,
        ];
    }

    /**
     * Resolves Google SERP snippet preview data for an entry.
     */
    public function resolveSnippetPreview(Entry $entry): array
    {
        $title = (string)$entry->title;
        $url = $entry->getUrl() ?? ($entry->getSite()->baseUrl . $entry->slug);

        // Resolve description from field layout
        $desc = '';
        $candidateHandles = ['seoDescription', 'metaDescription', 'description', 'summary', 'excerpt'];
        foreach ($candidateHandles as $handle) {
            if ($entry->getFieldLayout()?->getFieldByHandle($handle)) {
                $val = (string)$entry->getFieldValue($handle);
                if (!empty(trim($val))) {
                    $desc = trim(strip_tags($val));
                    break;
                }
            }
        }

        if (empty($desc)) {
            $desc = 'No meta description set for this page. Search engines will automatically construct a snippet from page text.';
        }

        return [
            'title' => $title,
            'url' => $url,
            'description' => $desc,
            'titleLength' => mb_strlen($title),
            'descriptionLength' => mb_strlen($desc),
        ];
    }
}
