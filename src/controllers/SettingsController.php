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

        return $this->renderTemplate('content-intelligence/settings/index', [
            'settings' => $plugin->getSettings(),
            'edition' => $plugin->getActiveEdition(),
            'hasPro' => $plugin->hasPro(),
            'hasAgency' => $plugin->hasAgency(),
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
        $settings = $this->request->getBodyParam('settings', []);

        if (!Craft::$app->getPlugins()->savePluginSettings($plugin, $settings)) {
            $this->setFailFlash(Craft::t('content-intelligence', 'Couldn’t save settings.'));
            return $this->redirectToPostedUrl();
        }

        $this->setSuccessFlash(Craft::t('content-intelligence', 'Settings saved successfully.'));
        return $this->redirectToPostedUrl();
    }
}
