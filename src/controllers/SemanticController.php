<?php

namespace abdulkadiragoliya\contentintelligence\controllers;

use Craft;
use craft\elements\Entry;
use craft\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use abdulkadiragoliya\contentintelligence\Plugin;
use abdulkadiragoliya\contentintelligence\jobs\IndexContentJob;

/**
 * Controller for Semantic Search, Chunking & Embeddings (Agency Edition).
 */
class SemanticController extends Controller
{
    /**
     * @inheritdoc
     */
    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        // Allow viewing index (which displays upgrade banner) and testing connection
        if (in_array($action->id, ['index', 'test-connection'], true)) {
            return true;
        }

        if (!Plugin::getInstance()->hasAgency()) {
            $this->response->data = [
                'success' => false,
                'message' => Craft::t('content-intelligence', 'Semantic Search & Embeddings require Content Intelligence Agency edition. Please upgrade your license to unlock.'),
                'upgradeRequired' => true,
            ];
            $this->response->format = \yii\web\Response::FORMAT_JSON;
            return false;
        }

        return true;
    }

    /**
     * Semantic index & knowledge base overview.
     */
    public function actionIndex(): Response
    {
        $this->requirePermission('contentIntelligence:manageKnowledgeBase');

        $plugin = Plugin::getInstance();
        $site = Craft::$app->getSites()->getCurrentSite();
        $hasAgency = $plugin->hasAgency();

        $stats = $hasAgency ? $plugin->vector->getIndexStats($site->id) : [
            'totalEntries' => 0,
            'indexedEntriesCount' => 0,
            'unindexedEntriesCount' => 0,
            'totalChunks' => 0,
            'coveragePercentage' => 0,
            'latestIndexedDate' => null,
            'avgChunksPerEntry' => 0,
        ];

        $entriesList = $hasAgency ? $plugin->vector->getIndexedEntriesList($site->id) : [];
        $health = $plugin->vector->getHealthStatus();

        return $this->renderTemplate('content-intelligence/semantic/index', [
            'edition' => $plugin->getActiveEdition(),
            'hasAgency' => $hasAgency,
            'currentSite' => $site,
            'stats' => $stats,
            'entriesList' => $entriesList,
            'vectorHealth' => $health,
        ]);
    }

    /**
     * Dispatch queue job to index entire site.
     */
    public function actionRunIndex(): Response
    {
        $this->requirePermission('contentIntelligence:manageKnowledgeBase');

        if (!Craft::$app->getRequest()->getIsPost()) {
            return $this->redirect('content-intelligence/semantic');
        }

        $plugin = Plugin::getInstance();
        if (!$plugin->hasAgency()) {
            $this->setFailFlash(Craft::t('content-intelligence', 'Semantic indexing requires Agency edition.'));
            return $this->redirectToPostedUrl(null, 'content-intelligence/semantic');
        }

        $site = Craft::$app->getSites()->getCurrentSite();

        Craft::$app->getQueue()->push(new IndexContentJob([
            'siteId' => $site->id,
            'generateVectors' => $plugin->ai->isConfigured(),
        ]));

        $this->setSuccessFlash(Craft::t('content-intelligence', 'Semantic indexing queued for all entries.'));
        return $this->redirectToPostedUrl(null, 'content-intelligence/semantic');
    }

    /**
     * Instantly chunk and index a single entry.
     */
    public function actionIndexEntry(?int $entryId = null): Response
    {
        $this->requirePermission('contentIntelligence:manageKnowledgeBase');

        $entryId = $entryId ?? (int)$this->request->getParam('entryId');

        $plugin = Plugin::getInstance();
        if (!$plugin->hasAgency()) {
            $this->setFailFlash(Craft::t('content-intelligence', 'Semantic indexing requires Agency edition.'));
            return $this->redirectToPostedUrl(null, 'content-intelligence/semantic');
        }

        $site = Craft::$app->getSites()->getCurrentSite();
        $entry = Entry::find()->id($entryId)->siteId($site->id)->status(null)->one();

        if (!$entry) {
            throw new NotFoundHttpException('Entry not found.');
        }

        $res = $plugin->vector->indexEntry($entry, $plugin->ai->isConfigured());

        $this->setSuccessFlash(Craft::t('content-intelligence', 'Indexed {count} chunks for "{title}" ({created} new, {unchanged} unchanged).', [
            'count' => $res['totalChunks'],
            'title' => $entry->title,
            'created' => $res['created'],
            'unchanged' => $res['unchanged'],
        ]));

        return $this->redirectToPostedUrl($entry, 'content-intelligence/semantic');
    }

    /**
     * Prune orphaned chunks from deleted entries.
     */
    public function actionPrune(): Response
    {
        $this->requirePermission('contentIntelligence:manageKnowledgeBase');

        if (!Craft::$app->getRequest()->getIsPost()) {
            return $this->redirect('content-intelligence/semantic');
        }

        $plugin = Plugin::getInstance();
        if (!$plugin->hasAgency()) {
            $this->setFailFlash(Craft::t('content-intelligence', 'Agency edition required.'));
            return $this->redirectToPostedUrl(null, 'content-intelligence/semantic');
        }

        $pruned = $plugin->vector->pruneOrphanedChunks();

        $this->setSuccessFlash(Craft::t('content-intelligence', 'Pruned {count} orphaned embedding chunks.', [
            'count' => $pruned,
        ]));

        return $this->redirectToPostedUrl(null, 'content-intelligence/semantic');
    }

    /**
     * Inspect individual entry chunk breakdown.
     */
    public function actionViewChunks(int $entryId): Response
    {
        $this->requirePermission('contentIntelligence:manageKnowledgeBase');

        $plugin = Plugin::getInstance();
        $site = Craft::$app->getSites()->getCurrentSite();

        $entry = Entry::find()->id($entryId)->siteId($site->id)->status(null)->one();
        if (!$entry) {
            throw new NotFoundHttpException('Entry not found.');
        }

        $chunks = $plugin->vector->getChunksForEntry($entryId, $site->id);

        return $this->renderTemplate('content-intelligence/semantic/chunks', [
            'entry' => $entry,
            'chunks' => $chunks,
            'edition' => $plugin->getActiveEdition(),
            'currentSite' => $site,
        ]);
    }

    /**
     * AJAX action: Perform Hybrid Search across indexed knowledge chunks.
     */
    public function actionSearch(): Response
    {
        $this->requirePostRequest();
        $this->requirePermission('contentIntelligence:manageKnowledgeBase');

        $plugin = Plugin::getInstance();
        if (!$plugin->hasAgency()) {
            return $this->asJson([
                'success' => false,
                'message' => Craft::t('content-intelligence', 'Hybrid search requires Agency edition.'),
            ]);
        }

        $query = (string)$this->request->getBodyParam('query', '');
        $limit = max(1, min(20, (int)$this->request->getBodyParam('limit', 5)));
        $site = Craft::$app->getSites()->getCurrentSite();

        if (empty(trim($query))) {
            return $this->asJson([
                'success' => true,
                'results' => [],
            ]);
        }

        try {
            $results = $plugin->vector->hybridSearch($query, $site->id, $limit);
            return $this->asJson([
                'success' => true,
                'query' => $query,
                'results' => $results,
            ]);
        } catch (\Throwable $e) {
            Craft::error("Semantic search failed: {$e->getMessage()}", __METHOD__);
            return $this->asJson([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * AJAX action: Test connection to Qdrant cluster.
     */
    public function actionTestConnection(): Response
    {
        $this->requirePostRequest();
        $this->requirePermission('contentIntelligence:manageSettings');

        $url = $this->request->getBodyParam('url');
        $apiKey = $this->request->getBodyParam('apiKey');

        $settings = Plugin::getInstance()->getSettings();
        $testUrl = $url ?: $settings->getQdrantUrl();
        $testKey = $apiKey !== null ? $apiKey : $settings->getQdrantApiKey();

        $client = new \abdulkadiragoliya\contentintelligence\services\vector\QdrantClient($testUrl, $testKey);

        if ($client->isReachable()) {
            $info = $client->getCollectionInfo($settings->qdrantCollection);
            $pointsCount = $info['points_count'] ?? 0;
            return $this->asJson([
                'success' => true,
                'message' => Craft::t('content-intelligence', 'Successfully reached Qdrant cluster at {url}. Collection "{collection}" contains {count} vector points.', [
                    'url' => $testUrl,
                    'collection' => $settings->qdrantCollection,
                    'count' => $pointsCount,
                ]),
            ]);
        }

        return $this->asJson([
            'success' => false,
            'message' => Craft::t('content-intelligence', 'Unable to connect to Qdrant at {url}. Ensure the cluster is online or check your API key.', [
                'url' => $testUrl,
            ]),
        ]);
    }
}

