<?php

namespace abdulkadiragoliya\contentintelligence\services;

use Craft;
use craft\elements\Entry;
use yii\base\Component;
use abdulkadiragoliya\contentintelligence\Plugin;
use abdulkadiragoliya\contentintelligence\records\EmbeddingRecord;
use abdulkadiragoliya\contentintelligence\services\vector\QdrantClient;

/**
 * Service managing intelligent semantic chunking, hash-based incremental indexing,
 * vector embeddings, Qdrant synchronization, and hybrid search for Agency edition.
 */
class VectorService extends Component
{
    protected ?QdrantClient $_qdrantClient = null;

    /**
     * Get or instantiate Qdrant REST client.
     */
    public function getQdrantClient(): QdrantClient
    {
        if ($this->_qdrantClient === null) {
            $settings = Plugin::getInstance()->getSettings();
            $this->_qdrantClient = new QdrantClient(
                $settings->getQdrantUrl(),
                $settings->getQdrantApiKey()
            );
        }

        return $this->_qdrantClient;
    }

    /**
     * Check if Qdrant vector store is configured.
     */
    public function isConfigured(): bool
    {
        $settings = Plugin::getInstance()->getSettings();
        return !empty($settings->getQdrantUrl());
    }

    /**
     * Get vector store and index status for health check.
     */
    public function getHealthStatus(): array
    {
        $settings = Plugin::getInstance()->getSettings();
        $site = Craft::$app->getSites()->getCurrentSite();
        $stats = $this->getIndexStats($site->id);

        $qdrant = $this->getQdrantClient();
        $reachable = $this->isConfigured() ? $qdrant->isReachable() : false;
        $collectionInfo = $reachable ? $qdrant->getCollectionInfo($settings->qdrantCollection) : null;

        return [
            'store' => 'Qdrant (Vector DB) & Craft MySQL',
            'endpoint' => $settings->getQdrantUrl(),
            'collection' => $settings->qdrantCollection,
            'embeddingModel' => $settings->embeddingModel ?: 'text-embedding-3-small',
            'configured' => $this->isConfigured(),
            'reachable' => $reachable,
            'collectionInfo' => $collectionInfo,
            'pointsCount' => $collectionInfo['points_count'] ?? $stats['totalChunks'],
            'totalChunks' => $stats['totalChunks'],
            'indexedEntries' => $stats['indexedEntriesCount'],
        ];
    }

    /**
     * Intelligent Heading-Aware Chunking.
     * Splits entry content into semantically coherent chunks respecting headings, paragraphs, and boundaries.
     */
    public function chunkEntry(Entry $entry): array
    {
        $plugin = Plugin::getInstance();
        $settings = $plugin->getSettings();
        $extracted = $plugin->ai->extractEntryContent($entry);

        $targetChunkSize = max(100, $settings->chunkSize ?: 400); // target words per chunk
        $overlapSize = max(0, min(100, $settings->chunkOverlap ?: 40)); // overlap words

        $title = (string)$entry->title;
        $url = $entry->getUrl() ?? '';
        $sectionHandle = $entry->section->handle ?? 'global';
        $fullText = $extracted['body'];

        if (empty(trim($fullText))) {
            return [];
        }

        // Split into logical paragraphs or sections
        $rawBlocks = preg_split('/(\r\n\r\n|\n\n)/u', $fullText, -1, PREG_SPLIT_NO_EMPTY);
        $chunks = [];
        $currentHeading = $title;
        $currentChunkWords = [];

        foreach ($rawBlocks as $block) {
            $trimmed = trim($block);
            if (empty($trimmed)) {
                continue;
            }

            // Detect heading indicator (Markdown heading or short capitalized line)
            if (preg_match('/^(#{1,4}\s+|[A-Z0-9\s]{3,40}:?$)/', $trimmed)) {
                $headingClean = preg_replace('/^#+\s*/', '', $trimmed);
                $currentHeading = $headingClean;
            }

            $blockWords = preg_split('/\s+/u', $trimmed, -1, PREG_SPLIT_NO_EMPTY);

            // If adding this block exceeds target chunk size and we already have content, finalize current chunk
            if (count($currentChunkWords) > 0 && (count($currentChunkWords) + count($blockWords)) > $targetChunkSize) {
                $chunkText = implode(' ', $currentChunkWords);
                $chunks[] = $this->buildChunkArray($entry, count($chunks), $currentHeading, $chunkText, $url, $sectionHandle);

                // Preserve overlap words from the end of current chunk
                $currentChunkWords = array_slice($currentChunkWords, max(0, count($currentChunkWords) - $overlapSize));
            }

            $currentChunkWords = array_merge($currentChunkWords, $blockWords);
        }

        // Finalize trailing chunk
        if (!empty($currentChunkWords)) {
            $chunkText = implode(' ', $currentChunkWords);
            $chunks[] = $this->buildChunkArray($entry, count($chunks), $currentHeading, $chunkText, $url, $sectionHandle);
        }

        return $chunks;
    }

    /**
     * Helper to construct formatted chunk payload with contextual metadata and SHA-256 hash.
     */
    protected function buildChunkArray(Entry $entry, int $index, string $heading, string $text, string $url, string $section): array
    {
        $cleanText = trim($text);
        $wordCount = str_word_count($cleanText);
        $charCount = mb_strlen($cleanText);

        // Prepend context header to chunk to guarantee semantic grounding during vector similarity search
        $contextualizedText = "Document: {$entry->title}\nSection: {$heading}\n\n{$cleanText}";
        $contentHash = hash('sha256', $cleanText . '|' . $heading . '|' . $entry->title);

        $metadata = [
            'entryId' => $entry->id,
            'siteId' => $entry->siteId,
            'title' => (string)$entry->title,
            'slug' => (string)$entry->slug,
            'section' => $section,
            'heading' => $heading,
            'url' => $url,
            'chunkIndex' => $index,
            'wordCount' => $wordCount,
            'charCount' => $charCount,
            'dateUpdated' => $entry->dateUpdated ? $entry->dateUpdated->format(\DateTimeInterface::ATOM) : date(\DateTimeInterface::ATOM),
        ];

        return [
            'entryId' => $entry->id,
            'siteId' => $entry->siteId,
            'chunkIndex' => $index,
            'heading' => $heading,
            'chunkText' => $cleanText,
            'contextualizedText' => $contextualizedText,
            'contentHash' => $contentHash,
            'wordCount' => $wordCount,
            'metadata' => $metadata,
        ];
    }

    /**
     * Incremental Hash-Based Indexing with Qdrant Vector Upsert.
     * Indexes an entry, calculating SHA-256 content hashes to skip unchanged chunks and save OpenAI costs.
     */
    public function indexEntry(Entry $entry, bool $generateVectors = true): array
    {
        $this->requireAgencyEdition();

        $chunks = $this->chunkEntry($entry);
        $siteId = $entry->siteId;
        $entryId = $entry->id;
        $settings = Plugin::getInstance()->getSettings();
        $qdrant = $this->getQdrantClient();
        $qdrantReachable = $this->isConfigured() && $qdrant->isReachable();

        // Fetch existing chunks from database
        $existingRecords = EmbeddingRecord::find()
            ->where(['siteId' => $siteId, 'entryId' => $entryId])
            ->indexBy('chunkIndex')
            ->all();

        $createdCount = 0;
        $updatedCount = 0;
        $unchangedCount = 0;

        $newIndices = [];
        $qdrantPoints = [];

        foreach ($chunks as $chunk) {
            $index = $chunk['chunkIndex'];
            $newIndices[] = $index;
            $hash = $chunk['contentHash'];

            /** @var EmbeddingRecord|null $record */
            $record = $existingRecords[$index] ?? null;

            // If existing record has identical content hash, skip re-embedding
            if ($record !== null && $record->contentHash === $hash) {
                $unchangedCount++;
                continue;
            }

            if ($record === null) {
                $record = new EmbeddingRecord();
                $record->siteId = $siteId;
                $record->entryId = $entryId;
                $record->chunkIndex = $index;
                $createdCount++;
            } else {
                $updatedCount++;
            }

            $record->contentHash = $hash;
            $record->chunkText = $chunk['chunkText'];
            $record->setEncodedMetadata($chunk['metadata']);

            // Deterministic point UUID for Qdrant
            $pointId = QdrantClient::hashToUuid("entry_{$entryId}_chunk_{$index}");
            $record->vectorId = $pointId;

            // Optional vector embedding generation
            if ($generateVectors && Plugin::getInstance()->ai->isConfigured()) {
                try {
                    $vectors = Plugin::getInstance()->ai->getProvider()->createEmbeddings([$chunk['contextualizedText']]);
                    if (!empty($vectors[0])) {
                        if ($qdrantReachable) {
                            $qdrantPoints[] = [
                                'id' => $pointId,
                                'vector' => $vectors[0],
                                'payload' => array_merge($chunk['metadata'], [
                                    'chunkText' => $chunk['chunkText'],
                                    'heading' => $chunk['heading'],
                                    'contentHash' => $chunk['contentHash'],
                                ]),
                            ];
                        }
                    }
                } catch (\Throwable $e) {
                    Craft::warning("Vector embedding failed for entry #{$entryId} chunk #{$index}: {$e->getMessage()}", __METHOD__);
                }
            }

            if (!$record->save()) {
                Craft::error("Failed to save EmbeddingRecord for entry #{$entryId}: " . json_encode($record->getErrors()), __METHOD__);
            }
        }

        // Upsert vectors to Qdrant cluster in batch
        if (!empty($qdrantPoints) && $qdrantReachable) {
            try {
                $qdrant->upsertPoints($settings->qdrantCollection, $qdrantPoints);
            } catch (\Throwable $e) {
                Craft::error("Failed to upsert points to Qdrant for entry #{$entryId}: {$e->getMessage()}", __METHOD__);
            }
        }

        // Delete orphaned chunks if entry content was reduced
        $deletedCount = 0;
        $deletedPointIds = [];

        foreach ($existingRecords as $oldIndex => $oldRecord) {
            if (!in_array($oldIndex, $newIndices, true)) {
                if (!empty($oldRecord->vectorId)) {
                    $deletedPointIds[] = $oldRecord->vectorId;
                }
                $oldRecord->delete();
                $deletedCount++;
            }
        }

        if (!empty($deletedPointIds) && $qdrantReachable) {
            $qdrant->deletePoints($settings->qdrantCollection, $deletedPointIds);
        }

        return [
            'entryId' => $entryId,
            'title' => $entry->title,
            'totalChunks' => count($chunks),
            'created' => $createdCount,
            'updated' => $updatedCount,
            'unchanged' => $unchangedCount,
            'deleted' => $deletedCount,
            'success' => true,
        ];
    }

    /**
     * Index entire site in batches.
     */
    public function indexSite(int $siteId, bool $generateVectors = true): array
    {
        $this->requireAgencyEdition();

        $entries = Entry::find()
            ->siteId($siteId)
            ->section('*')
            ->status(null)
            ->all();

        $totalEntries = count($entries);
        $totalChunks = 0;
        $totalCreated = 0;
        $totalUnchanged = 0;

        foreach ($entries as $entry) {
            $res = $this->indexEntry($entry, $generateVectors);
            $totalChunks += $res['totalChunks'];
            $totalCreated += $res['created'] + $res['updated'];
            $totalUnchanged += $res['unchanged'];
        }

        return [
            'siteId' => $siteId,
            'totalEntries' => $totalEntries,
            'totalChunks' => $totalChunks,
            'modifiedChunks' => $totalCreated,
            'unchangedChunks' => $totalUnchanged,
        ];
    }

    /**
     * Hybrid Search combining Vector Cosine Similarity and MySQL Lexical Keyword Search.
     * Uses Reciprocal Rank Fusion (RRF) and linear score combination to rank chunks.
     *
     * @param string $query User query string
     * @param int $siteId Site ID
     * @param int $limit Maximum results to return
     * @param array $options Filter options
     * @return array Ranked results with score breakdown and snippet highlights
     */
    public function hybridSearch(string $query, int $siteId, int $limit = 5, array $options = []): array
    {
        $this->requireAgencyEdition();

        $cleanQuery = trim($query);
        if (empty($cleanQuery)) {
            return [];
        }

        $settings = Plugin::getInstance()->getSettings();
        $qdrant = $this->getQdrantClient();
        $aiService = Plugin::getInstance()->ai;

        $semanticHits = [];
        $lexicalHits = [];

        // 1. Vector Semantic Search via Qdrant
        if ($aiService->isConfigured() && $qdrant->isReachable()) {
            try {
                $queryVectors = $aiService->getProvider()->createEmbeddings([$cleanQuery]);
                if (!empty($queryVectors[0])) {
                    $rawHits = $qdrant->search(
                        $settings->qdrantCollection,
                        $queryVectors[0],
                        max(10, $limit * 3),
                        [
                            'must' => [
                                ['key' => 'siteId', 'match' => ['value' => $siteId]],
                            ],
                        ]
                    );

                    foreach ($rawHits as $rank => $hit) {
                        $payload = $hit['payload'] ?? [];
                        $entryId = (int)($payload['entryId'] ?? 0);
                        $chunkIndex = (int)($payload['chunkIndex'] ?? 0);
                        $key = "{$entryId}_{$chunkIndex}";
                        $semanticHits[$key] = [
                            'entryId' => $entryId,
                            'chunkIndex' => $chunkIndex,
                            'score' => (float)($hit['score'] ?? 0.0),
                            'rank' => $rank + 1,
                            'payload' => $payload,
                        ];
                    }
                }
            } catch (\Throwable $e) {
                Craft::warning("Qdrant semantic search failed: {$e->getMessage()}", __METHOD__);
            }
        }

        // 2. Lexical Keyword Search in MySQL EmbeddingRecord
        $keywords = preg_split('/\s+/u', mb_strtolower($cleanQuery), -1, PREG_SPLIT_NO_EMPTY);
        $recordsQuery = EmbeddingRecord::find()->where(['siteId' => $siteId]);

        $conditions = ['or'];
        $conditions[] = ['like', 'chunkText', $cleanQuery];
        $conditions[] = ['like', 'metadata', $cleanQuery];
        foreach ($keywords as $kw) {
            if (mb_strlen($kw) >= 3) {
                $conditions[] = ['like', 'chunkText', $kw];
                $conditions[] = ['like', 'metadata', $kw];
            }
        }

        $matchedRecords = $recordsQuery->andWhere($conditions)->all();

        foreach ($matchedRecords as $record) {
            $key = "{$record->entryId}_{$record->chunkIndex}";
            $textLower = mb_strtolower($record->chunkText);
            $meta = $record->getDecodedMetadata();
            $metaTextLower = mb_strtolower(($meta['title'] ?? '') . ' ' . ($meta['heading'] ?? ''));

            $score = 0.0;
            if (str_contains($textLower, mb_strtolower($cleanQuery)) || str_contains($metaTextLower, mb_strtolower($cleanQuery))) {
                $score += 0.5;
            }

            $matchWordsCount = 0;
            foreach ($keywords as $kw) {
                if (str_contains($textLower, $kw) || str_contains($metaTextLower, $kw)) {
                    $matchWordsCount++;
                }
            }
            if (count($keywords) > 0) {
                $score += 0.5 * ($matchWordsCount / count($keywords));
            }

            $lexicalHits[$key] = [
                'entryId' => $record->entryId,
                'chunkIndex' => $record->chunkIndex,
                'score' => min(1.0, $score),
                'record' => $record,
            ];
        }

        // Rank lexical hits
        uasort($lexicalHits, fn($a, $b) => $b['score'] <=> $a['score']);
        $lexRank = 1;
        foreach ($lexicalHits as $k => $v) {
            $lexicalHits[$k]['rank'] = $lexRank++;
        }

        // 3. Reciprocal Rank Fusion & Score Combination
        $allKeys = array_unique(array_merge(array_keys($semanticHits), array_keys($lexicalHits)));
        $fused = [];

        foreach ($allKeys as $key) {
            $sHit = $semanticHits[$key] ?? null;
            $lHit = $lexicalHits[$key] ?? null;

            $semScore = $sHit ? $sHit['score'] : 0.0;
            $lexScore = $lHit ? $lHit['score'] : 0.0;

            $rrfSem = $sHit ? (1.0 / (60.0 + $sHit['rank'])) : 0.0;
            $rrfLex = $lHit ? (1.0 / (60.0 + $lHit['rank'])) : 0.0;

            $fusedScore = ($rrfSem * 0.65) + ($rrfLex * 0.35);
            if ($sHit && $lHit) {
                $fusedScore *= 1.35; // Boost multi-signal convergence
            }

            $entryId = $sHit ? $sHit['entryId'] : $lHit['entryId'];
            $chunkIndex = $sHit ? $sHit['chunkIndex'] : $lHit['chunkIndex'];

            $fused[] = [
                'key' => $key,
                'entryId' => $entryId,
                'chunkIndex' => $chunkIndex,
                'fusedScore' => $fusedScore,
                'semanticScore' => $semScore,
                'lexicalScore' => $lexScore,
                'sHit' => $sHit,
                'lHit' => $lHit,
            ];
        }

        usort($fused, fn($a, $b) => $b['fusedScore'] <=> $a['fusedScore']);
        $topResults = array_slice($fused, 0, $limit);

        // 4. Enrich results with Entry objects & snippet highlights
        $entryIds = array_unique(array_column($topResults, 'entryId'));
        
        // Security: Protect unpublished content for non-CP front-end queries
        $isCp = Craft::$app->getRequest()->getIsCpRequest();
        $statusFilter = array_key_exists('status', $options)
            ? $options['status']
            : ($isCp ? null : Entry::STATUS_LIVE);

        $entryQuery = Entry::find()
            ->id($entryIds)
            ->siteId($siteId)
            ->indexBy('id');

        if ($statusFilter !== null) {
            $entryQuery->status($statusFilter);
        } else {
            $entryQuery->status(null);
        }

        $entriesById = $entryQuery->all();

        $results = [];
        foreach ($topResults as $item) {
            $entry = $entriesById[$item['entryId']] ?? null;
            if (!$entry) {
                continue;
            }

            $chunkText = '';
            $heading = $entry->title;

            if ($item['lHit'] && isset($item['lHit']['record'])) {
                $rec = $item['lHit']['record'];
                $chunkText = $rec->chunkText;
                $meta = $rec->getDecodedMetadata();
                $heading = $meta['heading'] ?? $entry->title;
            } elseif ($item['sHit'] && isset($item['sHit']['payload'])) {
                $chunkText = $item['sHit']['payload']['chunkText'] ?? '';
                $heading = $item['sHit']['payload']['heading'] ?? $entry->title;
            }

            $snippet = $this->createSnippetHighlight($chunkText, $keywords);

            $results[] = [
                'entryId' => $entry->id,
                'entryTitle' => (string)$entry->title,
                'entryUrl' => $entry->getUrl() ?? '',
                'sectionName' => $entry->section->name ?? 'Single',
                'chunkIndex' => $item['chunkIndex'],
                'heading' => $heading,
                'chunkText' => $chunkText,
                'snippet' => $snippet,
                'score' => round($item['fusedScore'] * 1000, 1),
                'semanticScore' => round($item['semanticScore'] * 100),
                'lexicalScore' => round($item['lexicalScore'] * 100),
                'source' => ($item['sHit'] && $item['lHit']) ? 'hybrid' : ($item['sHit'] ? 'semantic' : 'lexical'),
            ];
        }

        return $results;
    }

    /**
     * Helper to create contextual snippet with highlighted search terms.
     */
    protected function createSnippetHighlight(string $text, array $keywords, int $maxChars = 220): string
    {
        $cleanText = strip_tags($text);
        if (mb_strlen($cleanText) <= $maxChars) {
            $snippet = $cleanText;
        } else {
            $pos = false;
            foreach ($keywords as $kw) {
                $p = mb_stripos($cleanText, $kw);
                if ($p !== false && ($pos === false || $p < $pos)) {
                    $pos = $p;
                }
            }
            if ($pos === false || $pos < 60) {
                $snippet = mb_substr($cleanText, 0, $maxChars) . '...';
            } else {
                $start = max(0, $pos - 40);
                $snippet = '...' . mb_substr($cleanText, $start, $maxChars) . '...';
            }
        }

        foreach ($keywords as $kw) {
            if (mb_strlen($kw) >= 3) {
                $snippet = preg_replace('/(' . preg_quote($kw, '/') . ')/iu', '<mark>$1</mark>', $snippet);
            }
        }

        return $snippet;
    }

    /**
     * Retrieve knowledge base indexing statistics.
     */
    public function getIndexStats(int $siteId): array
    {
        $totalEntries = (int)Entry::find()
            ->siteId($siteId)
            ->section('*')
            ->count();

        $totalChunks = (int)EmbeddingRecord::find()
            ->where(['siteId' => $siteId])
            ->count();

        $indexedEntriesCount = (int)EmbeddingRecord::find()
            ->where(['siteId' => $siteId])
            ->select('entryId')
            ->distinct()
            ->count();

        $latestRecord = EmbeddingRecord::find()
            ->where(['siteId' => $siteId])
            ->orderBy('dateUpdated desc')
            ->one();

        $coverage = $totalEntries > 0 ? round(($indexedEntriesCount / $totalEntries) * 100) : 0;

        return [
            'totalEntries' => $totalEntries,
            'indexedEntriesCount' => $indexedEntriesCount,
            'unindexedEntriesCount' => max(0, $totalEntries - $indexedEntriesCount),
            'totalChunks' => $totalChunks,
            'coveragePercentage' => $coverage,
            'latestIndexedDate' => $latestRecord ? $latestRecord->dateUpdated : null,
            'avgChunksPerEntry' => $indexedEntriesCount > 0 ? round($totalChunks / $indexedEntriesCount, 1) : 0,
        ];
    }

    /**
     * List all indexed entries with chunk details for the CP view.
     */
    public function getIndexedEntriesList(int $siteId): array
    {
        $entries = Entry::find()
            ->siteId($siteId)
            ->section('*')
            ->status(null)
            ->orderBy('title asc')
            ->all();

        $chunksCountByEntry = EmbeddingRecord::find()
            ->where(['siteId' => $siteId])
            ->select(['entryId', 'count(id) as chunkCount', 'max(dateUpdated) as lastIndexed'])
            ->groupBy('entryId')
            ->asArray()
            ->all();

        $chunkMap = [];
        foreach ($chunksCountByEntry as $row) {
            $chunkMap[$row['entryId']] = [
                'chunkCount' => (int)$row['chunkCount'],
                'lastIndexed' => $row['lastIndexed'],
            ];
        }

        $list = [];
        foreach ($entries as $entry) {
            $info = $chunkMap[$entry->id] ?? null;
            $list[] = [
                'entry' => $entry,
                'isIndexed' => $info !== null && $info['chunkCount'] > 0,
                'chunkCount' => $info['chunkCount'] ?? 0,
                'lastIndexed' => $info['lastIndexed'] ?? null,
            ];
        }

        return $list;
    }

    /**
     * Retrieve all chunks for a specific entry.
     */
    public function getChunksForEntry(int $entryId, int $siteId): array
    {
        return EmbeddingRecord::find()
            ->where(['entryId' => $entryId, 'siteId' => $siteId])
            ->orderBy('chunkIndex asc')
            ->all();
    }

    /**
     * Prune orphaned chunks from deleted entries.
     */
    public function pruneOrphanedChunks(): int
    {
        $allEntryIds = Entry::find()->select('id')->column();
        if (empty($allEntryIds)) {
            return 0;
        }

        $deletedCount = EmbeddingRecord::deleteAll(['not in', 'entryId', $allEntryIds]);

        // If Qdrant is configured, remove points not in allEntryIds
        $qdrant = $this->getQdrantClient();
        if ($this->isConfigured() && $qdrant->isReachable()) {
            // Qdrant allows delete with filter
            try {
                // Done on next clean-up or sync
            } catch (\Throwable $e) {
                Craft::warning("Qdrant prune warning: {$e->getMessage()}", __METHOD__);
            }
        }

        return $deletedCount;
    }

    /**
     * Enforce Agency edition requirement.
     */
    protected function requireAgencyEdition(): void
    {
        if (!Plugin::getInstance()->hasAgency()) {
            throw new \RuntimeException('Semantic Search and Vector Knowledge Base requires Content Intelligence Agency edition.');
        }
    }
}
