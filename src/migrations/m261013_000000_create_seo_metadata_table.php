<?php

namespace abdulkadiragoliya\contentintelligence\migrations;

use abdulkadiragoliya\contentintelligence\db\Table;
use craft\db\Migration;

/**
 * m261013_000000_create_seo_metadata_table migration.
 */
class m261013_000000_create_seo_metadata_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        if (!$this->db->tableExists(Table::SEO_METADATA)) {
            $this->createTable(Table::SEO_METADATA, [
                'id' => $this->primaryKey(),
                'siteId' => $this->integer()->notNull(),
                'elementId' => $this->integer()->notNull(),
                'seoTitle' => $this->string(255)->null(),
                'metaDescription' => $this->text()->null(),
                'isAiGenerated' => $this->boolean()->notNull()->defaultValue(false),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);

            $this->createIndex(null, Table::SEO_METADATA, ['siteId', 'elementId'], true);
            $this->createIndex(null, Table::SEO_METADATA, ['elementId']);

            $this->addForeignKey(
                null,
                Table::SEO_METADATA,
                'siteId',
                '{{%sites}}',
                'id',
                'CASCADE',
                'CASCADE'
            );

            $this->addForeignKey(
                null,
                Table::SEO_METADATA,
                'elementId',
                '{{%elements}}',
                'id',
                'CASCADE',
                'CASCADE'
            );
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        $this->dropTableIfExists(Table::SEO_METADATA);
        return true;
    }
}
