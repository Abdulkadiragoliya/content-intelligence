<?php

namespace abdulkadiragoliya\contentintelligence\services\vector;

use Craft;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ConnectException;

/**
 * Robust REST Client for Qdrant Vector Database (Bring-Your-Own-Cluster).
 * Supports local instances (http://localhost:6333) and Qdrant Cloud clusters.
 */
class QdrantClient
{
    protected Client $client;
    protected string $url;
    protected string $apiKey;

    public function __construct(?string $url = null, ?string $apiKey = null)
    {
        $rawUrl = rtrim($url ?: 'http://localhost:6333', '/');
        if (!static::isValidEndpoint($rawUrl)) {
            Craft::warning("Invalid or prohibited Qdrant URL '{$rawUrl}' rejected for SSRF protection. Falling back to default.", __METHOD__);
            $rawUrl = 'http://localhost:6333';
        }
        $this->url = $rawUrl;
        $this->apiKey = $apiKey ?: '';

        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        if (!empty($this->apiKey)) {
            $headers['api-key'] = $this->apiKey;
        }

        $this->client = new Client([
            'base_uri' => $this->url,
            'headers' => $headers,
            'timeout' => 8.0,
            'connect_timeout' => 3.0,
            'http_errors' => false,
        ]);
    }

    /**
     * SSRF validation: ensure URL uses http/https and blocks dangerous cloud metadata endpoints.
     */
    public static function isValidEndpoint(string $url): bool
    {
        $parsed = parse_url($url);
        if (!$parsed || empty($parsed['scheme']) || !in_array(strtolower($parsed['scheme']), ['http', 'https'], true)) {
            return false;
        }
        $host = strtolower($parsed['host'] ?? '');
        if ($host === '169.254.169.254' || $host === 'metadata.google.internal' || $host === 'instance-data') {
            return false;
        }
        return true;
    }

    /**
     * Check if Qdrant cluster is reachable.
     */
    public function isReachable(): bool
    {
        try {
            $response = $this->client->get('/healthz', ['timeout' => 2.0]);
            if ($response->getStatusCode() === 200) {
                return true;
            }

            // Fallback check to /collections or root
            $response = $this->client->get('/collections', ['timeout' => 2.0]);
            return $response->getStatusCode() === 200;
        } catch (\Throwable $e) {
            Craft::warning("Qdrant health check failed: {$e->getMessage()}", __METHOD__);
            return false;
        }
    }

    /**
     * Get detailed collection info.
     */
    public function getCollectionInfo(string $collectionName): ?array
    {
        try {
            $response = $this->client->get("/collections/{$collectionName}");
            if ($response->getStatusCode() === 200) {
                $body = json_decode((string)$response->getBody(), true);
                return $body['result'] ?? null;
            }
            return null;
        } catch (\Throwable $e) {
            Craft::warning("Qdrant getCollectionInfo failed: {$e->getMessage()}", __METHOD__);
            return null;
        }
    }

    /**
     * Ensure collection exists with specified vector dimensions and distance metric.
     */
    public function ensureCollection(string $collectionName, int $vectorSize = 1536, string $distance = 'Cosine'): bool
    {
        try {
            $info = $this->getCollectionInfo($collectionName);
            if ($info !== null) {
                return true;
            }

            // Create collection
            $payload = [
                'vectors' => [
                    'size' => $vectorSize,
                    'distance' => ucfirst(strtolower($distance)),
                ],
            ];

            $response = $this->client->put("/collections/{$collectionName}", [
                'json' => $payload,
            ]);

            return in_array($response->getStatusCode(), [200, 201], true);
        } catch (\Throwable $e) {
            Craft::error("Qdrant ensureCollection error: {$e->getMessage()}", __METHOD__);
            return false;
        }
    }

    /**
     * Upsert vector points into collection.
     *
     * @param string $collectionName
     * @param array $points Array of ['id' => string|int, 'vector' => float[], 'payload' => array]
     * @return bool
     */
    public function upsertPoints(string $collectionName, array $points): bool
    {
        if (empty($points)) {
            return true;
        }

        try {
            $this->ensureCollection($collectionName);

            $response = $this->client->put("/collections/{$collectionName}/points?wait=true", [
                'json' => [
                    'points' => $points,
                ],
            ]);

            $code = $response->getStatusCode();
            return $code === 200 || $code === 201;
        } catch (\Throwable $e) {
            Craft::error("Qdrant upsertPoints error: {$e->getMessage()}", __METHOD__);
            return false;
        }
    }

    /**
     * Delete points by point IDs.
     */
    public function deletePoints(string $collectionName, array $pointIds): bool
    {
        if (empty($pointIds)) {
            return true;
        }

        try {
            $response = $this->client->post("/collections/{$collectionName}/points/delete?wait=true", [
                'json' => [
                    'points' => array_values($pointIds),
                ],
            ]);

            return $response->getStatusCode() === 200;
        } catch (\Throwable $e) {
            Craft::error("Qdrant deletePoints error: {$e->getMessage()}", __METHOD__);
            return false;
        }
    }

    /**
     * Delete all points matching a specific filter (e.g. entryId).
     */
    public function deleteByEntryId(string $collectionName, int $entryId): bool
    {
        try {
            $response = $this->client->post("/collections/{$collectionName}/points/delete?wait=true", [
                'json' => [
                    'filter' => [
                        'must' => [
                            [
                                'key' => 'entryId',
                                'match' => ['value' => $entryId],
                            ],
                        ],
                    ],
                ],
            ]);

            return $response->getStatusCode() === 200;
        } catch (\Throwable $e) {
            Craft::error("Qdrant deleteByEntryId error: {$e->getMessage()}", __METHOD__);
            return false;
        }
    }

    /**
     * Perform vector similarity search.
     *
     * @param string $collectionName
     * @param float[] $vector
     * @param int $limit
     * @param array $filter
     * @return array Array of ['id' => ..., 'score' => ..., 'payload' => ...]
     */
    public function search(string $collectionName, array $vector, int $limit = 5, array $filter = []): array
    {
        try {
            $payload = [
                'vector' => $vector,
                'limit' => $limit,
                'with_payload' => true,
            ];

            if (!empty($filter)) {
                $payload['filter'] = $filter;
            }

            $response = $this->client->post("/collections/{$collectionName}/points/search", [
                'json' => $payload,
            ]);

            if ($response->getStatusCode() === 200) {
                $data = json_decode((string)$response->getBody(), true);
                return $data['result'] ?? [];
            }

            return [];
        } catch (\Throwable $e) {
            Craft::error("Qdrant search error: {$e->getMessage()}", __METHOD__);
            return [];
        }
    }

    /**
     * Convert any string hash to deterministic UUID (UUID v5 format) for Qdrant point IDs.
     */
    public static function hashToUuid(string $hash): string
    {
        $hex = substr(hash('sha256', $hash), 0, 32);
        return sprintf(
            '%08s-%04s-%04s-%04s-%12s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            '4' . substr($hex, 13, 3),
            '8' . substr($hex, 17, 3),
            substr($hex, 20, 12)
        );
    }
}
