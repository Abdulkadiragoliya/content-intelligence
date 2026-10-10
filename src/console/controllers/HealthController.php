<?php

namespace abdulkadiragoliya\contentintelligence\console\controllers;

use Craft;
use craft\console\Controller;
use craft\helpers\Console;
use yii\console\ExitCode;
use abdulkadiragoliya\contentintelligence\Plugin;

/**
 * Console controller for system health and diagnostics.
 */
class HealthController extends Controller
{
    /**
     * Check Content Intelligence system health and diagnostics.
     * Example: php craft content-intelligence/health/check
     */
    public function actionCheck(): int
    {
        $this->stdout("\n=== Content Intelligence Health Diagnostics ===\n", Console::FG_CYAN, Console::BOLD);

        $plugin = Plugin::getInstance();
        $settings = $plugin->getSettings();
        $site = Craft::$app->getSites()->getCurrentSite();

        // 1. Edition Check
        $edition = $plugin->getActiveEdition();
        $this->stdout("Edition: ", Console::BOLD);
        $this->stdout(strtoupper($edition) . "\n", Console::FG_PURPLE);

        // 2. AI Connection Diagnostics
        $this->stdout("\n[AI Provider - " . ucfirst($settings->aiProvider) . "]\n", Console::BOLD);
        $this->stdout(" - Configured Key: " . ($settings->getOpenaiApiKey() ? "Yes (Masked: " . $settings->getMaskedKey($settings->openaiApiKey) . ")" : "No") . "\n");
        $this->stdout(" - Model: {$settings->openaiModel}\n");

        if ($plugin->ai->isConfigured()) {
            $diag = $plugin->ai->testConnection();
            if ($diag['success']) {
                $this->stdout(" - API Connection: ONLINE (Latency: {$diag['latencyMs']}ms)\n", Console::FG_GREEN);
            } else {
                $this->stdout(" - API Connection: OFFLINE ({$diag['message']})\n", Console::FG_RED);
            }
        } else {
            $this->stdout(" - API Connection: Skipped (API Key not set)\n", Console::FG_YELLOW);
        }

        // 3. Vector DB Diagnostics (Plus)
        if ($plugin->hasPlus()) {
            $this->stdout("\n[Vector Database & Knowledge Base]\n", Console::BOLD);
            $health = $plugin->vector->getHealthStatus();
            $this->stdout(" - Storage Engine: {$health['store']}\n");
            $this->stdout(" - Qdrant Endpoint: {$health['endpoint']}\n");
            $this->stdout(" - Collection: {$health['collection']}\n");
            $this->stdout(" - Reachable: " . ($health['reachable'] ? "YES" : "NO (MySQL local fallback active)") . "\n", $health['reachable'] ? Console::FG_GREEN : Console::FG_YELLOW);

            $stats = $plugin->vector->getIndexStats($site->id);
            $this->stdout(" - Knowledge Coverage: {$stats['coveragePercentage']}%\n");
            $this->stdout(" - Total Chunks: {$stats['totalChunks']}\n");
            $this->stdout(" - Indexed Entries: {$stats['indexedEntriesCount']} / {$stats['totalEntries']}\n");
        }

        // 4. Audit Coverage
        $this->stdout("\n[Audit Status - Site #{$site->id}]\n", Console::BOLD);
        $auditStats = $plugin->audit->getAuditStats($site->id);
        $this->stdout(" - Total Site Entries: {$auditStats['totalEntries']}\n");
        $this->stdout(" - Audited Entries: {$auditStats['analyzedCount']}\n");
        $this->stdout(" - Average Content Score: {$auditStats['avgContentScore']}/100\n");
        $this->stdout(" - Average SEO Score: {$auditStats['avgSeoScore']}/100\n");
        $this->stdout(" - Critical Issues: {$auditStats['criticalIssues']}\n", $auditStats['criticalIssues'] > 0 ? Console::FG_RED : Console::FG_GREEN);

        $this->stdout("\nDiagnostics Complete.\n", Console::FG_CYAN, Console::BOLD);
        return ExitCode::OK;
    }
}
