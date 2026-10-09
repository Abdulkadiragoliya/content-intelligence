<?php

namespace abdulkadiragoliya\contentintelligence\records;

use craft\db\ActiveRecord;
use abdulkadiragoliya\contentintelligence\db\Table;
use yii\db\ActiveQuery;

/**
 * Active Record for audit findings.
 *
 * @property int $id
 * @property int $auditId
 * @property int $siteId
 * @property int|null $entryId
 * @property string $category
 * @property string $ruleId
 * @property string $severity
 * @property string $title
 * @property string|null $description
 * @property string|null $recommendation
 * @property string|null $context
 * @property string $dateCreated
 * @property string $dateUpdated
 * @property string $uid
 */
class AuditResultRecord extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return Table::AUDIT_RESULTS;
    }

    /**
     * Audit relation.
     */
    public function getAudit(): ActiveQuery
    {
        return $this->hasOne(AuditRecord::class, ['id' => 'auditId']);
    }
}
