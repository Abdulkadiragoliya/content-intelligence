<?php

namespace abdulkadiragoliya\contentintelligence\migrations;

use abdulkadiragoliya\contentintelligence\db\Table;
use craft\db\Migration;

/**
 * Install migration for Content Intelligence.
 */
class Install extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->createTables();
        $this->createIndexes();
        $this->addForeignKeys();

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        $this->dropTableIfExists(Table::EMBEDDINGS);
        $this->dropTableIfExists(Table::AUDIT_RESULTS);
        $this->dropTableIfExists(Table::AUDITS);

        return true;
    }

    /**
     * Creates database tables.
     */
    protected function createTables(): void
    {
        // 1. Audits Table
        if (!$this->db->tableExists(Table::AUDITS)) {
            $this->createTable(Table::AUDITS, [
                'id' => $this->primaryKey(),
                'siteId' => $this->integer()->notNull(),
                'entryId' => $this->integer()->null(),
                'contentScore' => $this->integer()->defaultValue(100),
                'seoScore' => $this->integer()->defaultValue(100),
                'overallScore' => $this->integer()->defaultValue(100),
                'criticalCount' => $this->integer()->defaultValue(0),
                'warningCount' => $this->integer()->defaultValue(0),
                'noticeCount' => $this->integer()->defaultValue(0),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);
        }

        // 2. Audit Results Table
        if (!$this->db->tableExists(Table::AUDIT_RESULTS)) {
            $this->createTable(Table::AUDIT_RESULTS, [
                'id' => $this->primaryKey(),
                'auditId' => $this->integer()->notNull(),
                'siteId' => $this->integer()->notNull(),
                'entryId' => $this->integer()->null(),
                'category' => $this->string(32)->notNull(),
                'ruleId' => $this->string(64)->notNull(),
                'severity' => $this->string(16)->notNull(),
                'title' => $this->string(255)->notNull(),
                'description' => $this->text(),
                'recommendation' => $this->text(),
                'context' => $this->text(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);
        }

        // 3. Embeddings Table
        if (!$this->db->tableExists(Table::EMBEDDINGS)) {
            $this->createTable(Table::EMBEDDINGS, [
                'id' => $this->primaryKey(),
                'siteId' => $this->integer()->notNull(),
                'entryId' => $this->integer()->notNull(),
                'chunkIndex' => $this->integer()->notNull()->defaultValue(0),
                'contentHash' => $this->char(64)->notNull(),
                'vectorId' => $this->string(64)->null(),
                'chunkText' => $this->mediumText(),
                'metadata' => $this->text(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);
        }
    }

    /**
     * Creates indexes.
     */
    protected function createIndexes(): void
    {
        $this->createIndex(null, Table::AUDITS, ['siteId', 'entryId']);
        $this->createIndex(null, Table::AUDITS, ['overallScore']);

        $this->createIndex(null, Table::AUDIT_RESULTS, ['auditId']);
        $this->createIndex(null, Table::AUDIT_RESULTS, ['siteId', 'entryId']);
        $this->createIndex(null, Table::AUDIT_RESULTS, ['category']);
        $this->createIndex(null, Table::AUDIT_RESULTS, ['severity']);
        $this->createIndex(null, Table::AUDIT_RESULTS, ['ruleId']);

        $this->createIndex(null, Table::EMBEDDINGS, ['siteId', 'entryId', 'chunkIndex'], true);
        $this->createIndex(null, Table::EMBEDDINGS, ['contentHash']);
    }

    /**
     * Adds foreign keys.
     */
    protected function addForeignKeys(): void
    {
        $this->addForeignKey(
            null,
            Table::AUDITS,
            'siteId',
            '{{%sites}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->addForeignKey(
            null,
            Table::AUDITS,
            'entryId',
            '{{%elements}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->addForeignKey(
            null,
            Table::AUDIT_RESULTS,
            'auditId',
            Table::AUDITS,
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->addForeignKey(
            null,
            Table::AUDIT_RESULTS,
            'siteId',
            '{{%sites}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->addForeignKey(
            null,
            Table::EMBEDDINGS,
            'siteId',
            '{{%sites}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->addForeignKey(
            null,
            Table::EMBEDDINGS,
            'entryId',
            '{{%elements}}',
            'id',
            'CASCADE',
            'CASCADE'
        );
    }
}
