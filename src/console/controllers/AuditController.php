<?php

namespace abdulkadiragoliya\contentintelligence\console\controllers;

use Craft;
use craft\console\Controller;
use craft\elements\Entry;
use craft\helpers\Console;
use yii\console\ExitCode;
use abdulkadiragoliya\contentintelligence\Plugin;

/**
 * Console controller for executing Content Intelligence audits via CLI / Cron.
 */
class AuditController extends Controller
{
    /**
     * @var string|null Target site handle or ID.
     */
    public ?string $site = null;

    /**
     * @var string|null Specific section handle to audit.
     */
    public ?string $section = null;

    /**
     * @inheritdoc
     */
    public function options($actionID): array
    {
        return array_merge(parent::options($actionID), ['site', 'section']);
    }

    /**
     * Run content & SEO audits across all entries.
     * Example: php craft content-intelligence/audit/all
     */
    public function actionAll(): int
    {
        $this->stdout("--- Content Intelligence Audit Runner ---\n", Console::FG_CYAN, Console::BOLD);

        $siteId = null;
        if ($this->site !== null) {
            $siteModel = Craft::$app->getSites()->getSiteByHandle($this->site);
            if (!$siteModel) {
                $this->stderr("Error: Site '{$this->site}' not found.\n", Console::FG_RED);
                return ExitCode::DATAERR;
            }
            $siteId = $siteModel->id;
        } else {
            $siteId = Craft::$app->getSites()->getCurrentSite()->id;
        }

        $query = Entry::find()
            ->siteId($siteId)
            ->section($this->section ?: '*')
            ->status(null);

        $total = (int)$query->count();
        if ($total === 0) {
            $this->stdout("No entries found matching criteria.\n", Console::FG_YELLOW);
            return ExitCode::OK;
        }

        $this->stdout("Auditing {$total} entries for site #{$siteId}...\n\n");

        $auditService = Plugin::getInstance()->audit;
        $count = 0;
        $totalOverall = 0;

        Console::startProgress(0, $total);

        foreach ($query->batch(25) as $entries) {
            foreach ($entries as $entry) {
                $count++;
                try {
                    $record = $auditService->auditEntry($entry);
                    $totalOverall += (int)$record->overallScore;
                } catch (\Throwable $e) {
                    Craft::error("CLI audit failed on entry #{$entry->id}: {$e->getMessage()}", __METHOD__);
                }
                Console::updateProgress($count, $total);
            }
        }

        Console::endProgress();

        $avgScore = $count > 0 ? round($totalOverall / $count, 1) : 0;

        $this->stdout("\nAudit complete!\n", Console::FG_GREEN, Console::BOLD);
        $this->stdout(" - Entries Audited: {$count}\n");
        $this->stdout(" - Average Overall Score: {$avgScore}/100\n");

        return ExitCode::OK;
    }
}
