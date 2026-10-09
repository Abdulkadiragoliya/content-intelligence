<?php

namespace abdulkadiragoliya\contentintelligence\services;

use Craft;
use yii\base\Component;
use craft\elements\Entry;
use craft\helpers\Json;
use abdulkadiragoliya\contentintelligence\Plugin;
use abdulkadiragoliya\contentintelligence\events\RegisterAuditRulesEvent;
use abdulkadiragoliya\contentintelligence\models\AuditResult;
use abdulkadiragoliya\contentintelligence\records\AuditRecord;
use abdulkadiragoliya\contentintelligence\records\AuditResultRecord;
use abdulkadiragoliya\contentintelligence\services\audit\AuditRuleInterface;
use abdulkadiragoliya\contentintelligence\services\audit\rules\MissingTitleRule;
use abdulkadiragoliya\contentintelligence\services\audit\rules\ThinContentRule;
use abdulkadiragoliya\contentintelligence\services\audit\rules\HeadingStructureRule;
use abdulkadiragoliya\contentintelligence\services\audit\rules\StaleContentRule;
use abdulkadiragoliya\contentintelligence\services\audit\rules\EmptyBodyRule;
use abdulkadiragoliya\contentintelligence\services\audit\rules\seo\MetaTitleRule;
use abdulkadiragoliya\contentintelligence\services\audit\rules\seo\MetaDescriptionRule;
use abdulkadiragoliya\contentintelligence\services\audit\rules\seo\ImageAltTextRule;
use abdulkadiragoliya\contentintelligence\services\audit\rules\seo\InternalLinkRule;
use abdulkadiragoliya\contentintelligence\services\audit\rules\seo\SingleH1Rule;

/**
 * Service managing rules-based Content and SEO audits.
 */
class AuditService extends Component
{
    public const EVENT_REGISTER_AUDIT_RULES = 'registerAuditRules';

    /**
     * @var AuditRuleInterface[]|null Cached rules list.
     */
    private ?array $_rules = null;

    /**
     * Returns all registered audit rules (default + event extensions).
     *
     * @return AuditRuleInterface[]
     */
    public function getRules(): array
    {
        if ($this->_rules !== null) {
            return $this->_rules;
        }

        $rules = [
            // Content Rules
            new MissingTitleRule(),
            new ThinContentRule(),
            new HeadingStructureRule(),
            new StaleContentRule(),
            new EmptyBodyRule(),

            // SEO Rules
            new MetaTitleRule(),
            new MetaDescriptionRule(),
            new ImageAltTextRule(),
            new InternalLinkRule(),
            new SingleH1Rule(),
        ];

        if ($this->hasEventHandlers(self::EVENT_REGISTER_AUDIT_RULES)) {
            $event = new RegisterAuditRulesEvent(['rules' => $rules]);
            $this->trigger(self::EVENT_REGISTER_AUDIT_RULES, $event);
            $rules = $event->rules;
        }

        $this->_rules = $rules;
        return $this->_rules;
    }

    /**
     * Run all audit rules against a single entry and persist results.
     *
     * @param Entry $entry
     * @return AuditRecord
     */
    public function auditEntry(Entry $entry): AuditRecord
    {
        $rules = $this->getRules();
        $results = [];

        foreach ($rules as $rule) {
            try {
                $res = $rule->run($entry);
                if ($res instanceof AuditResult) {
                    $results[] = $res;
                }
            } catch (\Throwable $e) {
                Craft::error("Error executing rule {$rule->getId()} on entry {$entry->id}: {$e->getMessage()}", __METHOD__);
            }
        }

        // Calculate deterministic score
        $scores = Plugin::getInstance()->score->calculateFromResults($results);

        // Find or create AuditRecord
        $auditRecord = AuditRecord::findOne([
            'siteId' => $entry->siteId,
            'entryId' => $entry->id,
        ]);

        if (!$auditRecord) {
            $auditRecord = new AuditRecord();
            $auditRecord->siteId = $entry->siteId;
            $auditRecord->entryId = $entry->id;
        }

        $auditRecord->contentScore = $scores['contentScore'];
        $auditRecord->seoScore = $scores['seoScore'];
        $auditRecord->overallScore = $scores['overallScore'];
        $auditRecord->criticalCount = $scores['criticalCount'];
        $auditRecord->warningCount = $scores['warningCount'];
        $auditRecord->noticeCount = $scores['noticeCount'];
        $auditRecord->save(false);

        // Clear and rewrite audit results for this audit
        AuditResultRecord::deleteAll(['auditId' => $auditRecord->id]);

        foreach ($results as $result) {
            $resultRecord = new AuditResultRecord();
            $resultRecord->auditId = $auditRecord->id;
            $resultRecord->siteId = $entry->siteId;
            $resultRecord->entryId = $entry->id;
            $resultRecord->category = $result->category;
            $resultRecord->ruleId = $result->ruleId;
            $resultRecord->severity = $result->severity;
            $resultRecord->title = $result->title;
            $resultRecord->description = $result->description;
            $resultRecord->recommendation = $result->recommendation;
            $resultRecord->context = !empty($result->context) ? Json::encode($result->context) : null;
            $resultRecord->save(false);
        }

        return $auditRecord;
    }

    /**
     * Retrieve the latest audit record for an entry.
     */
    public function getLatestAuditForEntry(int $entryId, int $siteId): ?AuditRecord
    {
        return AuditRecord::find()
            ->where(['entryId' => $entryId, 'siteId' => $siteId])
            ->with(['results'])
            ->one();
    }

    /**
     * Retrieve aggregate statistics across all audited entries for a site.
     */
    public function getAuditStats(?int $siteId = null): array
    {
        $siteId = $siteId ?? Craft::$app->getSites()->getCurrentSite()->id;
        $validIds = Entry::find()->siteId($siteId)->section('*')->status(null)->ids();
        $totalEntries = count($validIds);

        $audits = empty($validIds) ? [] : AuditRecord::find()->where(['siteId' => $siteId, 'entryId' => $validIds])->all();
        $analyzedCount = count($audits);

        if ($analyzedCount === 0) {
            return [
                'totalEntries' => $totalEntries,
                'analyzedCount' => 0,
                'avgContentScore' => 100,
                'avgSeoScore' => 100,
                'overallHealthScore' => 100,
                'criticalIssues' => 0,
                'warningIssues' => 0,
                'noticeIssues' => 0,
                'lastAuditDate' => null,
            ];
        }

        $totalContentScore = 0;
        $totalSeoScore = 0;
        $totalOverallScore = 0;
        $criticalIssues = 0;
        $warningIssues = 0;
        $noticeIssues = 0;
        $lastDate = null;

        foreach ($audits as $audit) {
            $totalContentScore += $audit->contentScore;
            $totalSeoScore += $audit->seoScore;
            $totalOverallScore += $audit->overallScore;
            $criticalIssues += $audit->criticalCount;
            $warningIssues += $audit->warningCount;
            $noticeIssues += $audit->noticeCount;

            if ($lastDate === null || $audit->dateUpdated > $lastDate) {
                $lastDate = $audit->dateUpdated;
            }
        }

        return [
            'totalEntries' => $totalEntries,
            'analyzedCount' => $analyzedCount,
            'avgContentScore' => round($totalContentScore / $analyzedCount),
            'avgSeoScore' => round($totalSeoScore / $analyzedCount),
            'overallHealthScore' => round($totalOverallScore / $analyzedCount),
            'criticalIssues' => $criticalIssues,
            'warningIssues' => $warningIssues,
            'noticeIssues' => $noticeIssues,
            'lastAuditDate' => $lastDate,
        ];
    }
}
