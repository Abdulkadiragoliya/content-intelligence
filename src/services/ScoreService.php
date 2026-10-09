<?php

namespace abdulkadiragoliya\contentintelligence\services;

use yii\base\Component;
use abdulkadiragoliya\contentintelligence\models\AuditResult;

/**
 * Service responsible for deterministic scoring calculations.
 */
class ScoreService extends Component
{
    /**
     * Compute a 0-100 score based on penalty weights.
     */
    public function calculateScore(int $criticalCount, int $warningCount, int $noticeCount): int
    {
        $penalties = ($criticalCount * 25) + ($warningCount * 10) + ($noticeCount * 3);
        return max(0, min(100, 100 - $penalties));
    }

    /**
     * Calculate category scores and counts from an array of AuditResult models.
     *
     * @param AuditResult[] $results
     * @return array
     */
    public function calculateFromResults(array $results): array
    {
        $contentCritical = 0;
        $contentWarning = 0;
        $contentNotice = 0;

        $seoCritical = 0;
        $seoWarning = 0;
        $seoNotice = 0;

        $totalCritical = 0;
        $totalWarning = 0;
        $totalNotice = 0;
        $totalGood = 0;

        foreach ($results as $result) {
            $sev = $result->severity;
            $cat = $result->category;

            if ($sev === AuditResult::SEVERITY_CRITICAL) {
                $totalCritical++;
                if ($cat === 'content') $contentCritical++;
                if ($cat === 'seo') $seoCritical++;
            } elseif ($sev === AuditResult::SEVERITY_WARNING) {
                $totalWarning++;
                if ($cat === 'content') $contentWarning++;
                if ($cat === 'seo') $seoWarning++;
            } elseif ($sev === AuditResult::SEVERITY_NOTICE) {
                $totalNotice++;
                if ($cat === 'content') $contentNotice++;
                if ($cat === 'seo') $seoNotice++;
            } elseif ($sev === AuditResult::SEVERITY_GOOD) {
                $totalGood++;
            }
        }

        $contentScore = $this->calculateScore($contentCritical, $contentWarning, $contentNotice);
        $seoScore = $this->calculateScore($seoCritical, $seoWarning, $seoNotice);
        $overallScore = $this->calculateScore($totalCritical, $totalWarning, $totalNotice);

        return [
            'contentScore' => $contentScore,
            'seoScore' => $seoScore,
            'overallScore' => $overallScore,
            'criticalCount' => $totalCritical,
            'warningCount' => $totalWarning,
            'noticeCount' => $totalNotice,
            'goodCount' => $totalGood,
        ];
    }
}
