<?php

namespace abdulkadiragoliya\contentintelligence\controllers;

use Craft;
use craft\web\Controller;
use yii\web\Response;
use abdulkadiragoliya\contentintelligence\Plugin;

/**
 * Settings controller for Content Intelligence.
 */
class SettingsController extends Controller
{
    /**
     * Display settings page.
     */
    public function actionIndex(): Response
    {
        $this->requirePermission('contentIntelligence:manageSettings');

        $plugin = Plugin::getInstance();
        $site = $plugin->getActiveSite();

        return $this->renderTemplate('content-intelligence/settings/index', [
            'settings' => $plugin->getSettings(),
            'currentSite' => $site,
            'edition' => $plugin->getActiveEdition(),
            'hasPro' => $plugin->hasPro(),
            'hasPlus' => $plugin->hasPlus(),
            'hasAgency' => $plugin->hasPlus(),
        ]);
    }

    /**
     * Save settings.
     */
    public function actionSave(): ?Response
    {
        $this->requirePostRequest();
        $this->requirePermission('contentIntelligence:manageSettings');

        $plugin = Plugin::getInstance();
        $postedSettings = $this->request->getBodyParam('settings', []);
        $siteId = (int)$this->request->getBodyParam('siteId');

        $currentSettings = $plugin->getSettings();
        $allSiteSettings = $currentSettings->siteSettings ?? [];

        if ($siteId) {
            $allSiteSettings[$siteId] = array_merge($allSiteSettings[$siteId] ?? [], $postedSettings);
            $finalSettings = $currentSettings->toArray();
            $finalSettings['siteSettings'] = $allSiteSettings;
        } else {
            $finalSettings = array_merge($currentSettings->toArray(), $postedSettings);
        }

        if (!Craft::$app->getPlugins()->savePluginSettings($plugin, $finalSettings)) {
            try {
                $this->setFailFlash(Craft::t('content-intelligence', 'Couldn’t save settings.'));
            } catch (\Throwable) {}
            return $this->redirectToPostedUrl();
        }

        try {
            $this->setSuccessFlash(Craft::t('content-intelligence', 'Settings saved successfully.'));
        } catch (\Throwable) {}
        return $this->redirectToPostedUrl();
    }
}
