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

    // Frontend SEO & Social Tag Output Settings
    public bool $renderCanonical = true;
    public bool $renderOpenGraph = true;
    public bool $renderTwitterCard = true;
    public string $twitterCardType = 'summary_large_image';

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
            [['enableAutoAuditOnSave', 'renderCanonical', 'renderOpenGraph', 'renderTwitterCard'], 'boolean'],
            [['aiProvider', 'openaiApiKey', 'openaiModel', 'vectorStore', 'qdrantUrl', 'qdrantApiKey', 'qdrantCollection', 'embeddingModel', 'twitterCardType'], 'string'],
            [['temperature'], 'number', 'min' => 0.0, 'max' => 1.0],
            [['siteSettings'], 'safe'],
        ];
    }

    /**
     * Default configuration properties.
     */
    public static function getDefaultProperty(string $property): mixed
    {
        $defaults = [
            'enableAutoAuditOnSave' => true,
            'minContentWordCount' => 300,
            'staleContentDays' => 180,
            'minTitleLength' => 30,
            'maxTitleLength' => 60,
            'minDescriptionLength' => 70,
            'maxDescriptionLength' => 160,
            'renderCanonical' => true,
            'renderOpenGraph' => true,
            'renderTwitterCard' => true,
            'twitterCardType' => 'summary_large_image',
            'aiProvider' => 'openai',
            'openaiApiKey' => '',
            'openaiModel' => 'gpt-4o-mini',
            'temperature' => 0.2,
            'vectorStore' => 'qdrant',
            'qdrantUrl' => 'http://localhost:6333',
            'qdrantApiKey' => '',
            'qdrantCollection' => 'content_intelligence',
            'embeddingModel' => 'text-embedding-3-small',
            'chunkSize' => 500,
            'chunkOverlap' => 50,
        ];
        return $defaults[$property] ?? null;
    }

    /**
     * Retrieve setting value with optional per-site override.
     */
    public function getForSite(string $property, ?int $siteId = null): mixed
    {
        if ($siteId !== null && isset($this->siteSettings[$siteId][$property])) {
            return $this->siteSettings[$siteId][$property];
        }

        $default = static::getDefaultProperty($property);
        if ($default !== null) {
            return $default;
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
