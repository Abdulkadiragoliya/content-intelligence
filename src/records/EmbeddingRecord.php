<?php

namespace abdulkadiragoliya\contentintelligence\records;

use craft\db\ActiveRecord;
use abdulkadiragoliya\contentintelligence\db\Table;

/**
 * Active Record for content intelligence embeddings / chunks table.
 *
 * @property int $id
 * @property int $siteId
 * @property int $entryId
 * @property int $chunkIndex
 * @property string $contentHash
 * @property string|null $vectorId
 * @property string $chunkText
 * @property string|null $metadata
 * @property string $dateCreated
 * @property string $dateUpdated
 * @property string $uid
 */
class EmbeddingRecord extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return Table::EMBEDDINGS;
    }

    /**
     * Returns decoded metadata array.
     */
    public function getDecodedMetadata(): array
    {
        if (empty($this->metadata)) {
            return [];
        }
        $decoded = json_decode($this->metadata, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Sets metadata as JSON string.
     */
    public function setEncodedMetadata(array $data): void
    {
        $this->metadata = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
