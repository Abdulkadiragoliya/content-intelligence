<?php

namespace abdulkadiragoliya\contentintelligence\console\controllers;

use Craft;
use craft\console\Controller;
use craft\helpers\Console;
use yii\console\ExitCode;
use abdulkadiragoliya\contentintelligence\Plugin;

/**
 * Console controller for managing semantic knowledge base and vector embeddings.
 */
class SemanticController extends Controller
{
    /**
     * @var string|null Target site handle.
     */
    public ?string $site = null;

    /**
     * @inheritdoc
     */
    public function options($actionID): array
    {
        return array_merge(parent::options($actionID), ['site']);
    }

    /**
     * Re-chunk and index all entries for semantic knowledge base.
     * Example: php craft content-intelligence/semantic/index-all
     */
    public function actionIndexAll(): int
    {
        $this->stdout("--- Content Intelligence Semantic Indexer ---\n", Console::FG_CYAN, Console::BOLD);

        $plugin = Plugin::getInstance();
        if (!$plugin->hasAgency()) {
            $this->stderr("Error: Semantic knowledge base indexing requires Content Intelligence Agency edition.\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $siteId = Craft::$app->getSites()->getCurrentSite()->id;
        if ($this->site !== null) {
            $siteModel = Craft::$app->getSites()->getSiteByHandle($this->site);
            if ($siteModel) {
                $siteId = $siteModel->id;
            }
        }

        $this->stdout("Indexing entries for site #{$siteId}...\n");

        $res = $plugin->vector->indexSite($siteId, $plugin->ai->isConfigured());

        $this->stdout("\nSemantic Indexing Complete!\n", Console::FG_GREEN, Console::BOLD);
        $this->stdout(" - Total Entries Processed: {$res['totalEntries']}\n");
        $this->stdout(" - Total Semantic Chunks: {$res['totalChunks']}\n");
        $this->stdout(" - Modified / New Chunks: {$res['modifiedChunks']}\n");
        $this->stdout(" - Unchanged (Cached) Chunks: {$res['unchangedChunks']}\n");

        return ExitCode::OK;
    }

    /**
     * Prune orphaned chunks from deleted entries.
     * Example: php craft content-intelligence/semantic/prune
     */
    public function actionPrune(): int
    {
        $this->stdout("Pruning orphaned knowledge chunks...\n", Console::FG_CYAN);

        $plugin = Plugin::getInstance();
        if (!$plugin->hasAgency()) {
            $this->stderr("Error: Requires Agency edition.\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $count = $plugin->vector->pruneOrphanedChunks();
        $this->stdout("Pruned {$count} orphaned chunks.\n", Console::FG_GREEN);

        return ExitCode::OK;
    }
}
