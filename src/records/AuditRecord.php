<?php

namespace abdulkadiragoliya\contentintelligence\records;

use craft\db\ActiveRecord;
use abdulkadiragoliya\contentintelligence\db\Table;
use yii\db\ActiveQuery;

/**
 * Active Record for content intelligence audits table.
 *
 * @property int $id
 * @property int $siteId
 * @property int|null $entryId
 * @property int $contentScore
 * @property int $seoScore
 * @property int $overallScore
 * @property int $criticalCount
 * @property int $warningCount
 * @property int $noticeCount
 * @property string $dateCreated
 * @property string $dateUpdated
 * @property string $uid
 */
class AuditRecord extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return Table::AUDITS;
    }

    /**
     * Results relation.
     */
    public function getResults(): ActiveQuery
    {
        return $this->hasMany(AuditResultRecord::class, ['auditId' => 'id']);
    }

    /**
     * Compute health status based on overall score.
     */
    public function getStatus(): string
    {
        if ($this->overallScore >= 80) {
            return 'good';
        }
        if ($this->overallScore >= 50) {
            return 'warning';
        }
        return 'danger';
    }

    /**
     * Virtual score attribute returning contentScore or overallScore.
     */
    public function getScore(): int
    {
        return (int)($this->contentScore ?? $this->overallScore ?? 0);
    }
}
