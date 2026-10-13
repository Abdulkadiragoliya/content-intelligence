<?php

namespace abdulkadiragoliya\contentintelligence\controllers;

use Craft;
use craft\elements\Entry;
use craft\web\Controller;
use yii\web\Response;
use abdulkadiragoliya\contentintelligence\Plugin;
use abdulkadiragoliya\contentintelligence\jobs\RunContentAuditJob;
use abdulkadiragoliya\contentintelligence\records\AuditRecord;
use abdulkadiragoliya\contentintelligence\records\AuditResultRecord;

/**
 * Controller for SEO Intelligence Audits and SERP analysis.
 */
class SeoController extends Controller
{
    /**
     * SEO audit overview table.
     */
    public function actionIndex(): Response
    {
        $this->requirePermission('contentIntelligence:runAudits');

        $plugin = Plugin::getInstance();
        $site = $plugin->getActiveSite();

        $entries = Entry::find()
            ->siteId($site->id)
            ->section('*')
            ->status(null)
            ->all();

        $audits = AuditRecord::find()
            ->where(['siteId' => $site->id])
            ->indexBy('entryId')
            ->all();

        return $this->renderTemplate('content-intelligence/seo/index', [
            'edition' => $plugin->getActiveEdition(),
            'currentSite' => $site,
            'entries' => $entries,
            'audits' => $audits,
            'stats' => $plugin->seo->getSeoStats($site->id),
        ]);
    }

    /**
     * Inspect individual entry SEO breakdown & SERP snippet preview.
     */
    public function actionView(int $entryId): Response
    {
        $this->requirePermission('contentIntelligence:runAudits');

        $plugin = Plugin::getInstance();
        $site = $plugin->getActiveSite();

        $entry = Craft::$app->getEntries()->getEntryById($entryId, $site->id)
            ?? Craft::$app->getElements()->getElementById($entryId, Entry::class, $site->id)
            ?? Entry::find()->id($entryId)->siteId($site->id)->status(null)->one();

        if (!$entry) {
            throw new \yii\web\NotFoundHttpException('Entry not found.');
        }

        $audit = $plugin->audit->getLatestAuditForEntry($entryId, $site->id);
        if (!$audit) {
            $audit = $plugin->audit->auditEntry($entry);
        }

        $seoResults = AuditResultRecord::find()
            ->where(['auditId' => $audit->id, 'category' => 'seo'])
            ->all();

        $serpPreview = $plugin->seo->resolveSnippetPreview($entry);

        return $this->renderTemplate('content-intelligence/seo/view', [
            'entry' => $entry,
            'audit' => $audit,
            'results' => $seoResults,
            'serp' => $serpPreview,
            'edition' => $plugin->getActiveEdition(),
            'currentSite' => $site,
            'hasPlus' => $plugin->hasPlus(),
            'hasPro' => $plugin->hasPro(),
            'aiHealth' => $plugin->ai->getHealthStatus(),
        ]);
    }

    /**
     * AJAX action to run comprehensive AI SEO Intelligence audit for an entry.
     */
    public function actionAnalyzeAiSeo(): Response
    {
        $this->requirePostRequest();
        $this->requirePermission('contentIntelligence:useAi');

        if (!Plugin::getInstance()->hasPlus()) {
            return $this->asJson([
                'success' => false,
                'message' => Craft::t('content-intelligence', 'AI SEO Analysis requires Content Intelligence Plus or Pro edition. Please upgrade your license to unlock.'),
                'upgradeRequired' => true,
            ]);
        }

        try {
            $request = Craft::$app->getRequest();
            $entryId = (int)$request->getBodyParam('entryId', 0);
            $targetKeyword = (string)$request->getBodyParam('targetKeyword', '');

            $site = Craft::$app->getSites()->getCurrentSite();
            $entry = Craft::$app->getEntries()->getEntryById($entryId, $site->id)
                ?? Craft::$app->getElements()->getElementById($entryId, Entry::class, $site->id)
                ?? Entry::find()->id($entryId)->siteId($site->id)->status(null)->one();
            if (!$entry) {
                return $this->asJson([
                    'success' => false,
                    'message' => "Entry #{$entryId} not found.",
                ]);
            }

            $plugin = Plugin::getInstance();
            $extracted = $plugin->ai->extractEntryContent($entry);

            $analysis = $plugin->ai->runAiSeoAudit(
                $extracted['title'],
                $extracted['body'],
                $targetKeyword
            );

            return $this->asJson([
                'success' => true,
                'analysis' => $analysis,
            ]);
        } catch (\Throwable $e) {
            Craft::error("AI SEO Analysis Error: {$e->getMessage()}", __METHOD__);
            return $this->asJson([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Queue a site-wide audit run.
     */
    public function actionRun(): Response
    {
        $this->requirePermission('contentIntelligence:runAudits');

        if (!Craft::$app->getRequest()->getIsPost()) {
            return $this->redirect('content-intelligence/seo');
        }

        $siteHandle = Craft::$app->getRequest()->getParam('site');
        $siteId = Craft::$app->getRequest()->getBodyParam('siteId');
        if ($siteId) {
            $site = Craft::$app->getSites()->getSiteById((int)$siteId);
        } elseif ($siteHandle) {
            $site = Craft::$app->getSites()->getSiteByHandle($siteHandle);
        } else {
            $site = Craft::$app->getSites()->getCurrentSite();
        }
        $site = $site ?? Craft::$app->getSites()->getCurrentSite();

        Craft::$app->getQueue()->push(new RunContentAuditJob([
            'siteId' => $site->id,
        ]));

        $this->setSuccessFlash(Craft::t('content-intelligence', 'SEO audit queued for site.'));
        return $this->redirect('content-intelligence/seo?site=' . $site->handle);
    }

    /**
     * AJAX action to retrieve saved standalone SEO metadata for an element.
     */
    public function actionGetMetadata(): Response
    {
        $this->requireAcceptsJson();

        $user = Craft::$app->getUser();
        if ($user->getIsGuest()) {
            throw new \yii\web\ForbiddenHttpException('Login required.');
        }

        $elementId = (int)$this->request->getParam('elementId');
        $siteId = (int)$this->request->getParam('siteId') ?: Craft::$app->getSites()->getCurrentSite()->id;

        if (!$elementId) {
            return $this->asJson([
                'success' => false,
                'message' => 'Element ID is required.',
            ]);
        }

        $element = Craft::$app->getElements()->getElementById($elementId, null, $siteId);
        if (!$element) {
            return $this->asJson([
                'success' => false,
                'message' => "Element #{$elementId} not found.",
            ]);
        }

        $lookupId = (int)($element->canonicalId ?: $element->id);
        $record = Plugin::getInstance()->seoMetadata->getMetadata($lookupId, $siteId);

        return $this->asJson([
            'success' => true,
            'metadata' => [
                'elementId' => $element->id,
                'canonicalId' => $lookupId,
                'siteId' => $siteId,
                'seoTitle' => $record?->seoTitle ?? '',
                'metaDescription' => $record?->metaDescription ?? '',
                'isAiGenerated' => (bool)($record?->isAiGenerated ?? false),
                'dateUpdated' => $record?->dateUpdated ?? null,
            ],
        ]);
    }

    /**
     * AJAX action to persistently save approved standalone SEO metadata for an element.
     */
    public function actionSaveMetadata(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $user = Craft::$app->getUser();
        if ($user->getIsGuest()) {
            throw new \yii\web\ForbiddenHttpException('Login required.');
        }

        $elementId = (int)$this->request->getBodyParam('elementId');
        $siteId = (int)$this->request->getBodyParam('siteId') ?: Craft::$app->getSites()->getCurrentSite()->id;
        $seoTitle = (string)$this->request->getBodyParam('seoTitle', '');
        $metaDescription = (string)$this->request->getBodyParam('metaDescription', '');
        $isAiGenerated = (bool)$this->request->getBodyParam('isAiGenerated', false);

        if (!$elementId) {
            return $this->asJson([
                'success' => false,
                'message' => 'Element ID is required.',
            ]);
        }

        $element = Craft::$app->getElements()->getElementById($elementId, null, $siteId);
        if (!$element) {
            return $this->asJson([
                'success' => false,
                'message' => "Element #{$elementId} not found.",
            ]);
        }

        // Authorization check for entry editing
        if ($element instanceof Entry && !empty($element->sectionId)) {
            if (!$user->getIsAdmin() && !$user->checkPermission('editEntries:' . $element->sectionId)) {
                throw new \yii\web\ForbiddenHttpException('You do not have permission to edit entries in this section.');
            }
        }

        // Validate title length limit
        if (mb_strlen($seoTitle) > 255) {
            return $this->asJson([
                'success' => false,
                'message' => 'SEO Title exceeds maximum database limit of 255 characters.',
            ]);
        }

        try {
            $lookupId = (int)($element->canonicalId ?: $element->id);
            $record = Plugin::getInstance()->seoMetadata->saveMetadata(
                $lookupId,
                $siteId,
                $seoTitle,
                $metaDescription,
                $isAiGenerated
            );

            // If it's an Entry, update audit so SEO scores reflect the newly saved metadata
            if ($element instanceof Entry && empty($element->getIsRevision())) {
                try {
                    Plugin::getInstance()->audit->auditEntry($element);
                } catch (\Throwable $e) {
                    Craft::warning("Re-audit on SEO metadata save failed: {$e->getMessage()}", __METHOD__);
                }
            }

            return $this->asJson([
                'success' => true,
                'message' => Craft::t('content-intelligence', 'SEO metadata saved successfully.'),
                'metadata' => [
                    'elementId' => $element->id,
                    'canonicalId' => $lookupId,
                    'siteId' => $siteId,
                    'seoTitle' => $record->seoTitle ?? '',
                    'metaDescription' => $record->metaDescription ?? '',
                    'isAiGenerated' => (bool)$record->isAiGenerated,
                    'dateUpdated' => $record->dateUpdated,
                ],
            ]);
        } catch (\Throwable $e) {
            Craft::error("Save SEO Metadata Error: {$e->getMessage()}", __METHOD__);
            return $this->asJson([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
