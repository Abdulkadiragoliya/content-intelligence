<?php

namespace abdulkadiragoliya\contentintelligence\services\ai;

/**
 * Interface for AI / Large Language Model providers.
 */
interface AiProviderInterface
{
    /**
     * Unique identifier of provider (e.g. 'openai').
     */
    public function getId(): string;

    /**
     * Human-readable provider name (e.g. 'OpenAI').
     */
    public function getName(): string;

    /**
     * Check if credentials and settings are configured.
     */
    public function isConfigured(): bool;

    /**
     * Test connection to provider API without performing expensive operations.
     *
     * @return array ['success' => bool, 'message' => string, 'latencyMs' => int, 'model' => string]
     */
    public function testConnection(): array;

    /**
     * Execute chat completion request.
     *
     * @param array $messages Array of ['role' => 'system'|'user'|'assistant', 'content' => string]
     * @param array $options Model override, temperature, max_tokens, etc.
     * @return array ['content' => string, 'usage' => array, 'model' => string]
     */
    public function chatCompletion(array $messages, array $options = []): array;

    /**
     * Request structured JSON completion guaranteed to return validated associative array.
     *
     * @param string $systemPrompt
     * @param string $userPrompt
     * @param array $options
     * @return array Parsed JSON data
     */
    public function jsonCompletion(string $systemPrompt, string $userPrompt, array $options = []): array;

    /**
     * Generate vector embeddings for text or array of texts.
     *
     * @param array|string $input Single string or array of strings to embed
     * @param array $options Model override, dimensions, etc.
     * @return array Array of float vectors, e.g. [[0.012, -0.045, ...]]
     */
    public function createEmbeddings(array|string $input, array $options = []): array;
}

