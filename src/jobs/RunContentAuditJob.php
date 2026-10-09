<?php

namespace abdulkadiragoliya\contentintelligence\jobs;

use Craft;
use craft\elements\Entry;
use craft\queue\BaseJob;
use abdulkadiragoliya\contentintelligence\Plugin;

/**
 * Queue job that runs content audits on entries in the background.
 */
class RunContentAuditJob extends BaseJob
{
    /**
     * @var int|null Target site ID.
     */
    public ?int $siteId = null;

    /**
     * @var array|null Specific entry IDs to audit, or null for all entries.
     */
    public ?array $entryIds = null;

    /**
     * @inheritdoc
     */
    public function execute($queue): void
    {
        $siteId = $this->siteId ?? Craft::$app->getSites()->getCurrentSite()->id;
        $auditService = Plugin::getInstance()->audit;

        $query = Entry::find()
            ->siteId($siteId)
            ->section('*')
            ->status(null); // Audit published and disabled section entries

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
                    Craft::t('content-intelligence', 'Auditing {step} of {total}: {title}', [
                        'step' => $step,
                        'total' => $total,
                        'title' => $entry->title ?? 'Untitled',
                    ])
                );

                try {
                    $auditService->auditEntry($entry);
                } catch (\Throwable $e) {
                    Craft::error("Queue audit failed on entry {$entry->id}: {$e->getMessage()}", __METHOD__);
                }
            }
        }
    }

    /**
     * @inheritdoc
     */
    protected function defaultDescription(): ?string
    {
        return Craft::t('content-intelligence', 'Running Content Intelligence Audit');
    }
}
