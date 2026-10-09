<?php

namespace abdulkadiragoliya\contentintelligence\controllers;

use Craft;
use craft\web\Controller;
use yii\web\Response;
use abdulkadiragoliya\contentintelligence\Plugin;

/**
 * Controller for Ask Your Website / RAG & Recommendations (Agency Edition).
 */
class RagController extends Controller
{
    /**
     * @inheritdoc
     */
    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        // Allow viewing index (which displays upgrade banner)
        if ($action->id === 'index') {
            return true;
        }

        if (!Plugin::getInstance()->hasAgency()) {
            $this->response->data = [
                'success' => false,
                'message' => Craft::t('content-intelligence', 'Ask Your Website RAG requires Content Intelligence Agency edition. Please upgrade your license to unlock.'),
                'upgradeRequired' => true,
            ];
            $this->response->format = \yii\web\Response::FORMAT_JSON;
            return false;
        }

        return true;
    }

    /**
     * Ask Your Website workspace index.
     */
    public function actionIndex(): Response
    {
        $this->requirePermission('contentIntelligence:askWebsite');

        $plugin = Plugin::getInstance();
        $site = Craft::$app->getSites()->getCurrentSite();

        return $this->renderTemplate('content-intelligence/rag/index', [
            'edition' => $plugin->getActiveEdition(),
            'hasAgency' => $plugin->hasAgency(),
            'currentSite' => $site,
            'aiConfigured' => $plugin->ai->isConfigured(),
            'vectorHealth' => $plugin->vector->getHealthStatus(),
        ]);
    }

    /**
     * AJAX action: Ask a question grounded in website knowledge chunks.
     */
    public function actionAsk(): Response
    {
        $this->requirePostRequest();
        $this->requirePermission('contentIntelligence:askWebsite');

        $plugin = Plugin::getInstance();
        if (!$plugin->hasAgency()) {
            return $this->asJson([
                'success' => false,
                'message' => Craft::t('content-intelligence', 'Ask Your Website RAG requires Agency edition.'),
            ]);
        }

        $question = (string)$this->request->getBodyParam('question', '');
        $site = Craft::$app->getSites()->getCurrentSite();

        if (empty(trim($question))) {
            return $this->asJson([
                'success' => false,
                'message' => Craft::t('content-intelligence', 'Please ask a question.'),
            ]);
        }

        $result = $plugin->rag->askWebsite($question, $site->id);
        return $this->asJson($result);
    }

    /**
     * AJAX action: Fetch semantically related recommendations for an entry.
     */
    public function actionRelated(int $entryId): Response
    {
        $this->requirePermission('contentIntelligence:askWebsite');

        $plugin = Plugin::getInstance();
        if (!$plugin->hasAgency()) {
            return $this->asJson([
                'success' => false,
                'message' => Craft::t('content-intelligence', 'Agency edition required.'),
            ]);
        }

        $site = Craft::$app->getSites()->getCurrentSite();
        $limit = max(1, min(10, (int)$this->request->getParam('limit', 4)));

        $recommendations = $plugin->rag->getRecommendations($entryId, $site->id, $limit);

        return $this->asJson([
            'success' => true,
            'entryId' => $entryId,
            'recommendations' => $recommendations,
        ]);
    }
}
