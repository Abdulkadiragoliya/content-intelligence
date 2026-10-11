<?php

namespace abdulkadiragoliya\contentintelligence\controllers;

use Craft;
use craft\web\Controller;
use yii\web\Response;
use abdulkadiragoliya\contentintelligence\Plugin;

/**
 * Dashboard controller for Content Intelligence.
 */
class DashboardController extends Controller
{
    /**
     * Dashboard main view.
     */
    public function actionIndex(): Response
    {
        $this->requirePermission('contentIntelligence:viewDashboard');

        $plugin = Plugin::getInstance();
        $site = $plugin->getActiveSite();
        $stats = $plugin->audit->getAuditStats($site->id);
        $edition = $plugin->getActiveEdition();

        return $this->renderTemplate('content-intelligence/dashboard/index', [
            'stats' => $stats,
            'edition' => $edition,
            'hasPro' => $plugin->hasPro(),
            'hasPlus' => $plugin->hasPlus(),
            'hasAgency' => $plugin->hasPro(),
            'currentSite' => $site,
        ]);
    }
}
