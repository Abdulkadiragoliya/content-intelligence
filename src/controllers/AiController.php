<?php

namespace abdulkadiragoliya\contentintelligence\controllers;

use Craft;
use craft\elements\Asset;
use craft\elements\Entry;
use craft\web\Controller;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use abdulkadiragoliya\contentintelligence\Plugin;

/**
 * Controller for AI Content Assistant, Diff workflows, and Diagnostics.
 */
class AiController extends Controller
{
    /**
     * @inheritdoc
     */
    protected array|bool|int $allowAnonymous = false;

    /**
     * @inheritdoc
     */
    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        // Allow viewing index workspace (which shows upgrade banner) and connection testing
        if (in_array($action->id, ['index', 'test-connection'], true)) {
            return true;
        }

        // All AI generation and execution endpoints strictly require Pro or Plus edition
        if (!Plugin::getInstance()->hasPro()) {
            $this->response->data = [
                'success' => false,
                'message' => Craft::t('content-intelligence', 'AI Assistant features require Content Intelligence Pro or Plus edition. Please upgrade your license to unlock.'),
                'upgradeRequired' => true,
            ];
            $this->response->format = Response::FORMAT_JSON;
            return false;
        }

        return true;
    }

    /**
     * AI Assistant full-page workspace and diagnostics.
     */
    public function actionIndex(): Response
    {
        $this->requirePermission('contentIntelligence:useAi');

        $plugin = Plugin::getInstance();
        $request = Craft::$app->getRequest();
        $selectedEntryId = (int)$request->getQueryParam('entryId', 0);

        // Fetch section entries (exclude internal matrix blocks)
        $entries = Entry::find()
            ->section('*')
            ->orderBy('title asc')
            ->all();

        $selectedEntry = null;
        $entryContent = null;
        if ($selectedEntryId) {
            $selectedEntry = Entry::find()->id($selectedEntryId)->one();
            if ($selectedEntry) {
                $entryContent = $plugin->ai->extractEntryContent($selectedEntry);
            }
        } elseif (!empty($entries)) {
            $selectedEntry = $entries[0];
            $entryContent = $plugin->ai->extractEntryContent($selectedEntry);
        }

        return $this->renderTemplate('content-intelligence/ai/index', [
            'edition' => $plugin->getActiveEdition(),
            'hasPro' => $plugin->hasPro(),
            'aiHealth' => $plugin->ai->getHealthStatus(),
            'entries' => $entries,
            'selectedEntry' => $selectedEntry,
            'entryContent' => $entryContent,
        ]);
    }

    /**
     * Resolve entry element supporting canonical entries, drafts, revisions, and unsaved changes.
     */
    protected function findEntry(int $entryId, ?int $siteId = null): ?Entry
    {
        $siteId = $siteId ?: Craft::$app->getSites()->getCurrentSite()->id;
        return Craft::$app->getEntries()->getEntryById($entryId, $siteId)
            ?? Craft::$app->getElements()->getElementById($entryId, Entry::class, $siteId)
            ?? Entry::find()->id($entryId)->siteId($siteId)->status(null)->one();
    }

    /**
     * Slideout modal for Entry Editor integration.
     */
    public function actionSidebarModal(?int $entryId = null): Response
    {
        $this->requirePermission('contentIntelligence:useAi');

        $entryId = $entryId ?? (int)$this->request->getParam('entryId');
        $siteId = (int)$this->request->getParam('siteId') ?: Craft::$app->getSites()->getCurrentSite()->id;
        $entry = $this->findEntry($entryId, $siteId);
        if (!$entry) {
            throw new NotFoundHttpException("Entry #{$entryId} not found");
        }

        $plugin = Plugin::getInstance();
        $entryContent = $plugin->ai->extractEntryContent($entry);
        $aiHealth = $plugin->ai->getHealthStatus();

        return $this->renderTemplate('content-intelligence/_cp/slideout', [
            'entry' => $entry,
            'entryContent' => $entryContent,
            'hasPro' => $plugin->hasPro(),
            'aiHealth' => $aiHealth,
            'siteId' => $siteId,
        ]);
    }

    /**
     * AJAX action to test AI provider connection.
     */
    public function actionTestConnection(): Response
    {
        $this->requirePostRequest();
        $this->requirePermission('contentIntelligence:manageSettings');

        $result = Plugin::getInstance()->ai->testConnection();
        return $this->asJson($result);
    }

    /**
     * Generate SEO meta title and description suggestions.
     */
    public function actionGenerateMeta(): Response
    {
        $this->requirePostRequest();
        $this->requirePermission('contentIntelligence:useAi');

        try {
            $request = Craft::$app->getRequest();
            $entryId = (int)$request->getBodyParam('entryId', 0);
            $siteId = (int)$request->getBodyParam('siteId', 0) ?: Craft::$app->getSites()->getCurrentSite()->id;
            $title = (string)$request->getBodyParam('title', '');
            $content = (string)$request->getBodyParam('content', '');

            if ($entryId && (empty($title) || empty($content))) {
                $entry = $this->findEntry($entryId, $siteId);
                if ($entry) {
                    $extracted = Plugin::getInstance()->ai->extractEntryContent($entry);
                    $title = $title ?: $extracted['title'];
                    $content = $content ?: $extracted['body'];
                }
            }

            if (empty($content)) {
                return $this->asJson([
                    'success' => false,
                    'message' => 'Please provide page title and content to generate meta tags.',
                ]);
            }

            $ai = Plugin::getInstance()->ai;
            $titlesResult = $ai->generateMetaTitles($title, $content);
            $descsResult = $ai->generateMetaDescriptions($title, $content);

            return $this->asJson([
                'success' => true,
                'titles' => $titlesResult['suggestions'] ?? [],
                'descriptions' => $descsResult['suggestions'] ?? [],
            ]);
        } catch (\Throwable $e) {
            Craft::error("AI Generate Meta Error: {$e->getMessage()}", __METHOD__);
            return $this->asJson([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Rewrite and improve content with side-by-side diff.
     */
    public function actionImproveContent(): Response
    {
        $this->requirePostRequest();
        $this->requirePermission('contentIntelligence:useAi');

        try {
            $request = Craft::$app->getRequest();
            $entryId = (int)$request->getBodyParam('entryId', 0);
            $siteId = (int)$request->getBodyParam('siteId', 0) ?: Craft::$app->getSites()->getCurrentSite()->id;
            $title = (string)$request->getBodyParam('title', '');
            $content = (string)$request->getBodyParam('content', '');
            $instructions = (string)$request->getBodyParam('instructions', '');
            $tone = (string)$request->getBodyParam('tone', 'professional');

            if ($entryId && empty($content)) {
                $entry = $this->findEntry($entryId, $siteId);
                if ($entry) {
                    $extracted = Plugin::getInstance()->ai->extractEntryContent($entry);
                    $title = $title ?: $extracted['title'];
                    $content = $extracted['body'];
                }
            }

            if (empty($content)) {
                return $this->asJson([
                    'success' => false,
                    'message' => 'Content cannot be empty to improve.',
                ]);
            }

            $ai = Plugin::getInstance()->ai;
            $result = $ai->improveContent($title, $content, $instructions, $tone);

            return $this->asJson([
                'success' => true,
                'improvedContent' => $result['improvedContent'] ?? '',
                'improvementsMade' => $result['improvementsMade'] ?? [],
                'diff' => $result['diff'] ?? null,
                'wordCountChange' => $result['wordCountChange'] ?? '',
            ]);
        } catch (\Throwable $e) {
            Craft::error("AI Improve Content Error: {$e->getMessage()}", __METHOD__);
            return $this->asJson([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Generate FAQs from content.
     */
    public function actionGenerateFaqs(): Response
    {
        $this->requirePostRequest();
        $this->requirePermission('contentIntelligence:useAi');

        try {
            $request = Craft::$app->getRequest();
            $entryId = (int)$request->getBodyParam('entryId', 0);
            $siteId = (int)$request->getBodyParam('siteId', 0) ?: Craft::$app->getSites()->getCurrentSite()->id;
            $title = (string)$request->getBodyParam('title', '');
            $content = (string)$request->getBodyParam('content', '');

            if ($entryId && empty($content)) {
                $entry = $this->findEntry($entryId, $siteId);
                if ($entry) {
                    $extracted = Plugin::getInstance()->ai->extractEntryContent($entry);
                    $title = $title ?: $extracted['title'];
                    $content = $extracted['body'];
                }
            }

            if (empty($content)) {
                return $this->asJson([
                    'success' => false,
                    'message' => 'Content is required to generate FAQs.',
                ]);
            }

            $ai = Plugin::getInstance()->ai;
            $result = $ai->generateFaqs($title, $content);

            return $this->asJson([
                'success' => true,
                'faqs' => $result['faqs'] ?? [],
            ]);
        } catch (\Throwable $e) {
            Craft::error("AI Generate FAQs Error: {$e->getMessage()}", __METHOD__);
            return $this->asJson([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Generate Executive Summary and Social snippets.
     */
    public function actionGenerateSummary(): Response
    {
        $this->requirePostRequest();
        $this->requirePermission('contentIntelligence:useAi');

        try {
            $request = Craft::$app->getRequest();
            $entryId = (int)$request->getBodyParam('entryId', 0);
            $siteId = (int)$request->getBodyParam('siteId', 0) ?: Craft::$app->getSites()->getCurrentSite()->id;
            $title = (string)$request->getBodyParam('title', '');
            $content = (string)$request->getBodyParam('content', '');

            if ($entryId && empty($content)) {
                $entry = $this->findEntry($entryId, $siteId);
                if ($entry) {
                    $extracted = Plugin::getInstance()->ai->extractEntryContent($entry);
                    $title = $title ?: $extracted['title'];
                    $content = $extracted['body'];
                }
            }

            if (empty($content)) {
                return $this->asJson([
                    'success' => false,
                    'message' => 'Content is required to generate summary.',
                ]);
            }

            $ai = Plugin::getInstance()->ai;
            $result = $ai->generateSummary($title, $content);

            return $this->asJson([
                'success' => true,
                'summary' => $result,
            ]);
        } catch (\Throwable $e) {
            Craft::error("AI Generate Summary Error: {$e->getMessage()}", __METHOD__);
            return $this->asJson([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Generate Image Alt Text.
     */
    public function actionGenerateAlt(): Response
    {
        $this->requirePostRequest();
        $this->requirePermission('contentIntelligence:useAi');

        try {
            $request = Craft::$app->getRequest();
            $assetId = (int)$request->getBodyParam('assetId', 0);
            $context = (string)$request->getBodyParam('context', '');
            $filename = (string)$request->getBodyParam('filename', '');

            if ($assetId) {
                $asset = Asset::find()->id($assetId)->one();
                if ($asset) {
                    $filename = $asset->filename;
                    $context = $context ?: ($asset->title ?: $filename);
                }
            }

            if (empty($context) && empty($filename)) {
                return $this->asJson([
                    'success' => false,
                    'message' => 'Image filename or surrounding context is required.',
                ]);
            }

            $ai = Plugin::getInstance()->ai;
            $result = $ai->generateAltText($context, $filename);

            return $this->asJson([
                'success' => true,
                'altText' => $result['altText'] ?? '',
                'explanation' => $result['explanation'] ?? '',
            ]);
        } catch (\Throwable $e) {
            Craft::error("AI Generate Alt Error: {$e->getMessage()}", __METHOD__);
            return $this->asJson([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * AI SEO Intelligence analysis (Search Intent, Topic Coverage & Gaps, Semantic Keywords, Differentiation).
     */
    public function actionAnalyzeSeo(): Response
    {
        $this->requirePostRequest();
        $this->requirePermission('contentIntelligence:useAi');

        try {
            $request = Craft::$app->getRequest();
            $entryId = (int)$request->getBodyParam('entryId', 0);
            $siteId = (int)$request->getBodyParam('siteId', 0) ?: Craft::$app->getSites()->getCurrentSite()->id;
            $targetKeyword = (string)$request->getBodyParam('targetKeyword', '');
            $title = (string)$request->getBodyParam('title', '');
            $content = (string)$request->getBodyParam('content', '');

            if ($entryId && (empty($title) || empty($content))) {
                $entry = $this->findEntry($entryId, $siteId);
                if ($entry) {
                    $extracted = Plugin::getInstance()->ai->extractEntryContent($entry);
                    $title = $title ?: $extracted['title'];
                    $content = $content ?: $extracted['body'];
                }
            }

            if (empty($content)) {
                return $this->asJson([
                    'success' => false,
                    'message' => 'Content is required to analyze SEO intelligence.',
                ]);
            }

            $ai = Plugin::getInstance()->ai;
            $analysis = $ai->runAiSeoAudit($title, $content, $targetKeyword);

            return $this->asJson([
                'success' => true,
                'analysis' => $analysis,
            ]);
        } catch (\Throwable $e) {
            Craft::error("AI SEO Intelligence Error: {$e->getMessage()}", __METHOD__);
            return $this->asJson([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }
}

