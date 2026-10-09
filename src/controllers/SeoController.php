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
        $site = Craft::$app->getSites()->getCurrentSite();

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
        $site = Craft::$app->getSites()->getCurrentSite();

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

        if (!Plugin::getInstance()->hasPro()) {
            return $this->asJson([
                'success' => false,
                'message' => Craft::t('content-intelligence', 'AI SEO Analysis requires Content Intelligence Pro or Agency edition. Please upgrade your license to unlock.'),
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

        $site = Craft::$app->getSites()->getCurrentSite();

        Craft::$app->getQueue()->push(new RunContentAuditJob([
            'siteId' => $site->id,
        ]));

        $this->setSuccessFlash(Craft::t('content-intelligence', 'SEO audit queued for site.'));
        return $this->redirectToPostedUrl(null, 'content-intelligence/seo');
    }
}
