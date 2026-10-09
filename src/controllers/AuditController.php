<?php

namespace abdulkadiragoliya\contentintelligence\controllers;

use Craft;
use craft\elements\Entry;
use craft\web\Controller;
use yii\web\Response;
use abdulkadiragoliya\contentintelligence\Plugin;
use abdulkadiragoliya\contentintelligence\jobs\RunContentAuditJob;
use abdulkadiragoliya\contentintelligence\records\AuditRecord;

/**
 * Controller for Content Audits.
 */
class AuditController extends Controller
{
    /**
     * Content audit overview table.
     */
    public function actionIndex(): Response
    {
        $this->requirePermission('contentIntelligence:runAudits');

        $plugin = Plugin::getInstance();
        $site = Craft::$app->getSites()->getCurrentSite();

        // Retrieve all entries in current site
        $entries = Entry::find()
            ->siteId($site->id)
            ->section('*')
            ->status(null)
            ->all();

        // Map latest audits indexed by entryId
        $audits = AuditRecord::find()
            ->where(['siteId' => $site->id])
            ->indexBy('entryId')
            ->all();

        return $this->renderTemplate('content-intelligence/audit/index', [
            'edition' => $plugin->getActiveEdition(),
            'currentSite' => $site,
            'entries' => $entries,
            'audits' => $audits,
            'stats' => $plugin->audit->getAuditStats($site->id),
        ]);
    }

    /**
     * Inspect individual entry audit breakdown.
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
            // Run audit now if not yet audited
            $audit = $plugin->audit->auditEntry($entry);
        }

        return $this->renderTemplate('content-intelligence/audit/view', [
            'entry' => $entry,
            'audit' => $audit,
            'results' => $audit->results ?? [],
            'edition' => $plugin->getActiveEdition(),
            'currentSite' => $site,
        ]);
    }

    /**
     * Trigger background audit scan across the site.
     */
    public function actionRun(): Response
    {
        $this->requirePermission('contentIntelligence:runAudits');

        if (!Craft::$app->getRequest()->getIsPost()) {
            return $this->redirect('content-intelligence/audit');
        }

        $site = Craft::$app->getSites()->getCurrentSite();

        Craft::$app->getQueue()->push(new RunContentAuditJob([
            'siteId' => $site->id,
        ]));

        $this->setSuccessFlash(Craft::t('content-intelligence', 'Content audit queued for processing.'));
        return $this->redirectToPostedUrl(null, 'content-intelligence/audit');
    }

    /**
     * Instantly re-audit a single entry.
     */
    public function actionAuditEntry(?int $entryId = null): Response
    {
        $this->requirePermission('contentIntelligence:runAudits');

        $entryId = $entryId ?? (int)$this->request->getParam('entryId');
        $entry = Craft::$app->getEntries()->getEntryById($entryId, $site->id)
            ?? Craft::$app->getElements()->getElementById($entryId, Entry::class, $site->id)
            ?? Entry::find()->id($entryId)->siteId($site->id)->status(null)->one();

        if (!$entry) {
            throw new \yii\web\NotFoundHttpException('Entry not found.');
        }

        Plugin::getInstance()->audit->auditEntry($entry);

        $this->setSuccessFlash(Craft::t('content-intelligence', 'Entry audited successfully.'));
        return $this->redirectToPostedUrl($entry, 'content-intelligence/audit/' . $entryId);
    }

    /**
     * Agency Reporting: Export complete Audit Report as CSV.
     */
    public function actionExport(): Response
    {
        $this->requirePermission('contentIntelligence:runAudits');

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

        $filename = 'content-audit-' . $site->handle . '-' . date('Y-m-d') . '.csv';

        $output = fopen('php://temp', 'w');
        // UTF-8 BOM for Excel compatibility
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($output, [
            'Entry ID',
            'Title',
            'Section',
            'Status',
            'Content Score',
            'SEO Score',
            'Overall Score',
            'Critical Issues',
            'Warning Issues',
            'Notice Issues',
            'Last Audit Date',
        ]);

        foreach ($entries as $entry) {
            /** @var AuditRecord|null $audit */
            $audit = $audits[$entry->id] ?? null;
            fputcsv($output, [
                $entry->id,
                $entry->title,
                $entry->section->name ?? 'Single',
                $entry->getStatus(),
                $audit ? $audit->contentScore : 'Not Audited',
                $audit ? $audit->seoScore : 'Not Audited',
                $audit ? $audit->overallScore : 'Not Audited',
                $audit ? $audit->criticalCount : 0,
                $audit ? $audit->warningCount : 0,
                $audit ? $audit->noticeCount : 0,
                $audit && $audit->dateUpdated ? $audit->dateUpdated : 'N/A',
            ]);
        }

        rewind($output);
        $csvContent = stream_get_contents($output);
        fclose($output);

        return $this->response->sendContentAsFile($csvContent, $filename, [
            'mimeType' => 'text/csv',
        ]);
    }
}
