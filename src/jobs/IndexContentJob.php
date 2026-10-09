<?php

namespace abdulkadiragoliya\contentintelligence\jobs;

use Craft;
use craft\elements\Entry;
use craft\queue\BaseJob;
use abdulkadiragoliya\contentintelligence\Plugin;

/**
 * Queue job that breaks entries into semantic chunks and builds vector indexes in the background.
 */
class IndexContentJob extends BaseJob
{
    /**
     * @var int|null Target site ID.
     */
    public ?int $siteId = null;

    /**
     * @var array|null Specific entry IDs to index, or null for all entries.
     */
    public ?array $entryIds = null;

    /**
     * @var bool Whether to request vector embeddings during indexing.
     */
    public bool $generateVectors = true;

    /**
     * @inheritdoc
     */
    public function execute($queue): void
    {
        $siteId = $this->siteId ?? Craft::$app->getSites()->getCurrentSite()->id;
        $vectorService = Plugin::getInstance()->vector;

        $query = Entry::find()
            ->siteId($siteId)
            ->section('*')
            ->status(null);

        if (!empty($this->entryIds)) {
            $query->id($this->entryIds);
        }

        $total = (int)$query->count();
        if ($total === 0) {
            return;
        }

        $step = 0;
        $batchSize = 25;

        foreach ($query->batch($batchSize) as $entries) {
            foreach ($entries as $entry) {
                $step++;
                $this->setProgress(
                    $queue,
                    $step / $total,
                    Craft::t('content-intelligence', 'Indexing chunk {step} of {total}: {title}', [
                        'step' => $step,
                        'total' => $total,
                        'title' => $entry->title ?? 'Untitled',
                    ])
                );

                try {
                    $vectorService->indexEntry($entry, $this->generateVectors);
                } catch (\Throwable $e) {
                    Craft::error("Queue semantic indexing failed on entry #{$entry->id}: {$e->getMessage()}", __METHOD__);
                }
            }
        }
    }

    /**
     * @inheritdoc
     */
    protected function defaultDescription(): ?string
    {
        return Craft::t('content-intelligence', 'Indexing Content for Semantic Knowledge Base');
    }
}
