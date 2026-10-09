<?php

namespace abdulkadiragoliya\contentintelligence\services\ai;

use Craft;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\ServerException;
use craft\helpers\Json;
use abdulkadiragoliya\contentintelligence\Plugin;

/**
 * OpenAI implementation of AiProviderInterface (BYOK - Bring Your Own Key).
 */
class OpenAiProvider implements AiProviderInterface
{
    protected const API_BASE = 'https://api.openai.com/v1';

    /**
     * @var Client|null
     */
    private ?Client $_client = null;

    public function getId(): string
    {
        return 'openai';
    }

    public function getName(): string
    {
        return 'OpenAI';
    }

    public function isConfigured(): bool
    {
        $settings = Plugin::getInstance()->getSettings();
        return !empty(trim($settings->getOpenaiApiKey()));
    }

    /**
     * Tests API credentials with a lightweight models check.
     */
    public function testConnection(): array
    {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'message' => Craft::t('content-intelligence', 'OpenAI API key is not configured.'),
                'latencyMs' => 0,
                'model' => '',
            ];
        }

        $startTime = microtime(true);
        $client = $this->getClient();
        $settings = Plugin::getInstance()->getSettings();
        $model = $settings->openaiModel ?: 'gpt-4o-mini';

        try {
            // Lightweight model retrieval request
            $response = $client->get('models/' . urlencode($model));
            $latency = (int)round((microtime(true) - $startTime) * 1000);

            if ($response->getStatusCode() === 200) {
                return [
                    'success' => true,
                    'message' => Craft::t('content-intelligence', 'Successfully connected to OpenAI ({model}) in {ms}ms.', [
                        'model' => $model,
                        'ms' => $latency,
                    ]),
                    'latencyMs' => $latency,
                    'model' => $model,
                ];
            }

            return [
                'success' => false,
                'message' => Craft::t('content-intelligence', 'Unexpected response code: {code}', ['code' => $response->getStatusCode()]),
                'latencyMs' => $latency,
                'model' => $model,
            ];
        } catch (ClientException $e) {
            $code = $e->getResponse()?->getStatusCode();
            $msg = $this->parseErrorMessage($e);

            if ($code === 401) {
                $msg = Craft::t('content-intelligence', 'Authentication failed. Please verify your OpenAI API key.');
            } elseif ($code === 404) {
                $msg = Craft::t('content-intelligence', 'Model "{model}" not found or your account does not have access.', ['model' => $model]);
            } elseif ($code === 429) {
                $msg = Craft::t('content-intelligence', 'OpenAI rate limit reached or quota exhausted. Please check your OpenAI billing plan.');
            }

            return [
                'success' => false,
                'message' => $msg,
                'latencyMs' => 0,
                'model' => $model,
            ];
        } catch (ConnectException $e) {
            return [
                'success' => false,
                'message' => Craft::t('content-intelligence', 'Connection to OpenAI timed out or network error.'),
                'latencyMs' => 0,
                'model' => $model,
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => Craft::t('content-intelligence', 'Connection test failed: {msg}', ['msg' => $e->getMessage()]),
                'latencyMs' => 0,
                'model' => $model,
            ];
        }
    }

    /**
     * Executes standard chat completion.
     */
    public function chatCompletion(array $messages, array $options = []): array
    {
        $settings = Plugin::getInstance()->getSettings();
        $model = $options['model'] ?? ($settings->openaiModel ?: 'gpt-4o-mini');
        $temperature = $options['temperature'] ?? (float)$settings->temperature;

        $payload = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => $temperature,
        ];

        if (isset($options['response_format'])) {
            $payload['response_format'] = $options['response_format'];
        }

        if (isset($options['max_tokens'])) {
            $payload['max_tokens'] = (int)$options['max_tokens'];
        }

        try {
            $client = $this->getClient();
            $response = $client->post('chat/completions', [
                'json' => $payload,
            ]);

            $data = Json::decodeIfJson((string)$response->getBody());
            $choice = $data['choices'][0] ?? null;

            if (!$choice) {
                throw new \RuntimeException('No completion choice returned from OpenAI.');
            }

            return [
                'content' => $choice['message']['content'] ?? '',
                'usage' => $data['usage'] ?? [],
                'model' => $data['model'] ?? $model,
            ];
        } catch (ClientException $e) {
            throw new \RuntimeException($this->parseErrorMessage($e), $e->getCode(), $e);
        } catch (\Throwable $e) {
            Craft::error("OpenAI completion failure: {$e->getMessage()}", __METHOD__);
            throw $e;
        }
    }

    /**
     * Executes JSON-constrained completion and returns parsed array.
     */
    public function jsonCompletion(string $systemPrompt, string $userPrompt, array $options = []): array
    {
        $messages = [
            [
                'role' => 'system',
                'content' => $systemPrompt . "\nIMPORTANT: You must respond exclusively with valid JSON matching the requested structure. Do not wrap with markdown or code fences.",
            ],
            [
                'role' => 'user',
                'content' => $userPrompt,
            ],
        ];

        $options['response_format'] = ['type' => 'json_object'];

        $result = $this->chatCompletion($messages, $options);
        $raw = trim($result['content'] ?? '');

        // Sanitize any extraneous markdown wrappers if present
        if (str_starts_with($raw, '```json')) {
            $raw = substr($raw, 7);
        } elseif (str_starts_with($raw, '```')) {
            $raw = substr($raw, 3);
        }
        if (str_ends_with($raw, '```')) {
            $raw = substr($raw, 0, -3);
        }

        $decoded = Json::decodeIfJson(trim($raw));
        if (!is_array($decoded)) {
            throw new \RuntimeException('OpenAI failed to return valid JSON output.');
        }

        return $decoded;
    }

    /**
     * Generate vector embeddings via OpenAI API.
     */
    public function createEmbeddings(array|string $input, array $options = []): array
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('OpenAI API key is not configured.');
        }

        $settings = Plugin::getInstance()->getSettings();
        $model = $options['model'] ?? ($settings->embeddingModel ?: 'text-embedding-3-small');
        $inputs = is_array($input) ? array_values($input) : [$input];

        if (empty($inputs)) {
            return [];
        }

        $payload = [
            'model' => $model,
            'input' => $inputs,
        ];

        try {
            $client = $this->getClient();
            $response = $client->post('embeddings', [
                'json' => $payload,
            ]);

            $data = Json::decode((string)$response->getBody());
            $results = [];

            if (isset($data['data']) && is_array($data['data'])) {
                foreach ($data['data'] as $item) {
                    $results[] = $item['embedding'] ?? [];
                }
            }

            return $results;
        } catch (ClientException $e) {
            $msg = $this->parseErrorMessage($e);
            Craft::error("OpenAI Embedding ClientException: {$msg}", __METHOD__);
            throw new \RuntimeException("OpenAI embedding generation failed: {$msg}");
        } catch (\Throwable $e) {
            Craft::error("OpenAI Embedding Throwable: {$e->getMessage()}", __METHOD__);
            throw new \RuntimeException("OpenAI embedding failed: {$e->getMessage()}");
        }
    }

    /**
     * Constructs pre-authenticated Guzzle HTTP client with timeouts.
     */
    protected function getClient(): Client
    {
        if ($this->_client !== null) {
            return $this->_client;
        }

        $settings = Plugin::getInstance()->getSettings();
        $apiKey = $settings->getOpenaiApiKey();

        $this->_client = new Client([
            'base_uri' => self::API_BASE . '/',
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
                'User-Agent' => 'CraftCMS-ContentIntelligence/1.0',
            ],
            'timeout' => 30.0,
            'connect_timeout' => 10.0,
        ]);

        return $this->_client;
    }

    /**
     * Safely parse OpenAI JSON error message without exposing credentials.
     */
    protected function parseErrorMessage(ClientException $e): string
    {
        $body = (string)$e->getResponse()?->getBody();
        $data = Json::decodeIfJson($body);

        if (is_array($data) && isset($data['error']['message'])) {
            return (string)$data['error']['message'];
        }

        return $e->getMessage();
    }
}
