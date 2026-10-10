<?php

namespace abdulkadiragoliya\contentintelligence\models;

use craft\base\Model;
use craft\helpers\App;

/**
 * Settings model for Content Intelligence.
 */
class Settings extends Model
{
    // General Settings
    public bool $enableAutoAuditOnSave = true;
    public int $minContentWordCount = 300;
    public int $staleContentDays = 180;

    // SEO Settings
    public int $minTitleLength = 30;
    public int $maxTitleLength = 60;
    public int $minDescriptionLength = 70;
    public int $maxDescriptionLength = 160;

    // AI Settings (Pro & Plus)
    public string $aiProvider = 'openai';
    public string $openaiApiKey = '';
    public string $openaiModel = 'gpt-4o-mini';
    public float $temperature = 0.2;

    // Vector / Semantic Settings (Plus)
    public string $vectorStore = 'qdrant';
    public string $qdrantUrl = 'http://localhost:6333';
    public string $qdrantApiKey = '';
    public string $qdrantCollection = 'content_intelligence';
    public string $embeddingModel = 'text-embedding-3-small';
    public int $chunkSize = 500;
    public int $chunkOverlap = 50;

    // Multi-site Plus Overrides
    public array $siteSettings = [];

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [
            [['minContentWordCount', 'staleContentDays', 'minTitleLength', 'maxTitleLength', 'minDescriptionLength', 'maxDescriptionLength', 'chunkSize', 'chunkOverlap'], 'integer'],
            [['enableAutoAuditOnSave'], 'boolean'],
            [['aiProvider', 'openaiApiKey', 'openaiModel', 'vectorStore', 'qdrantUrl', 'qdrantApiKey', 'qdrantCollection', 'embeddingModel'], 'string'],
            [['temperature'], 'number', 'min' => 0.0, 'max' => 1.0],
            [['siteSettings'], 'safe'],
        ];
    }

    /**
     * Retrieve setting value with optional per-site override.
     */
    public function getForSite(string $property, ?int $siteId = null): mixed
    {
        if ($siteId !== null && isset($this->siteSettings[$siteId][$property])) {
            return $this->siteSettings[$siteId][$property];
        }
        return $this->$property ?? null;
    }

    /**
     * Returns the parsed OpenAI API key.
     */
    public function getOpenaiApiKey(): string
    {
        return App::parseEnv($this->openaiApiKey) ?? '';
    }

    /**
     * Returns the parsed Qdrant URL.
     */
    public function getQdrantUrl(): string
    {
        return App::parseEnv($this->qdrantUrl) ?? 'http://localhost:6333';
    }

    /**
     * Returns the parsed Qdrant API key.
     */
    public function getQdrantApiKey(): string
    {
        return App::parseEnv($this->qdrantApiKey) ?? '';
    }

    /**
     * Returns a masked API key for secure display in the CP.
     */
    public function getMaskedKey(string $key): string
    {
        $parsed = App::parseEnv($key);
        if (empty($parsed)) {
            return '';
        }
        $length = strlen($parsed);
        if ($length <= 8) {
            return str_repeat('*', $length);
        }
        return substr($parsed, 0, 3) . str_repeat('*', $length - 7) . substr($parsed, -4);
    }
}
