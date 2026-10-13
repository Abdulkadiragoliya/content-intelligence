<?php

namespace abdulkadiragoliya\contentintelligence\records;

use craft\db\ActiveRecord;
use abdulkadiragoliya\contentintelligence\db\Table;
use yii\db\ActiveQuery;

/**
 * Active Record for Content Intelligence SEO metadata table.
 *
 * @property int $id
 * @property int $siteId
 * @property int $elementId
 * @property string|null $seoTitle
 * @property string|null $metaDescription
 * @property bool $isAiGenerated
 * @property string $dateCreated
 * @property string $dateUpdated
 * @property string $uid
 */
class SeoMetadataRecord extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return Table::SEO_METADATA;
    }

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [
            [['siteId', 'elementId'], 'required'],
            [['siteId', 'elementId'], 'integer'],
            [['seoTitle'], 'string', 'max' => 255],
            [['metaDescription'], 'string'],
            [['isAiGenerated'], 'boolean'],
        ];
    }
}
