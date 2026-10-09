<?php

namespace abdulkadiragoliya\contentintelligence;

use Craft;
use craft\base\Element;
use craft\base\Model;
use craft\base\Plugin as BasePlugin;
use craft\elements\Entry;
use craft\events\DefineHtmlEvent;
use craft\events\ModelEvent;
use craft\events\RegisterCpNavItemsEvent;
use craft\events\RegisterTemplateRootsEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\helpers\App;
use craft\helpers\UrlHelper;
use craft\services\UserPermissions;
use craft\web\UrlManager;
use craft\web\View;
use craft\web\twig\variables\Cp;
use yii\base\Event;

use abdulkadiragoliya\contentintelligence\models\Settings;
use abdulkadiragoliya\contentintelligence\services\AuditService;
use abdulkadiragoliya\contentintelligence\services\ScoreService;
use abdulkadiragoliya\contentintelligence\services\SeoService;
use abdulkadiragoliya\contentintelligence\services\AiService;
use abdulkadiragoliya\contentintelligence\services\VectorService;
use abdulkadiragoliya\contentintelligence\services\RagService;
use abdulkadiragoliya\contentintelligence\web\assets\cp\CpAsset;
use abdulkadiragoliya\contentintelligence\web\twig\ContentIntelligenceTwigExtension;
use abdulkadiragoliya\contentintelligence\web\twig\variables\ContentIntelligenceVariable;
use craft\web\twig\variables\CraftVariable;

/**
 * Content Intelligence plugin class.
 *
 * @property AuditService $audit
 * @property ScoreService $score
 * @property SeoService $seo
 * @property AiService $ai
 * @property VectorService $vector
 * @property RagService $rag
 */
class Plugin extends BasePlugin
{
    public const EDITION_LITE = 'lite';
    public const EDITION_PRO = 'pro';
    public const EDITION_AGENCY = 'agency';

    public string $schemaVersion = '1.0.0';
    public bool $hasCpSection = true;
    public bool $hasCpSettings = true;

    /**
     * @inheritdoc
     */
    public static function editions(): array
    {
        return [
            self::EDITION_LITE,
            self::EDITION_PRO,
            self::EDITION_AGENCY,
        ];
    }

    /**
     * @inheritdoc
     */
    public function init(): void
    {
        parent::init();

        if (Craft::$app->getRequest()->getIsConsoleRequest()) {
            $this->controllerNamespace = 'abdulkadiragoliya\\contentintelligence\\console\\controllers';
        }

        $this->registerComponents();
        $this->registerCpAssets();
        $this->registerCpRoutes();
        $this->registerTemplateRoots();
        $this->registerPermissions();
        $this->registerElementEvents();
        $this->registerTwigExtensions();
        $this->registerVariables();
    }

    /**
     * Get the active plugin edition.
     */
    public function getActiveEdition(): string
    {
        $override = App::env('CONTENT_INTELLIGENCE_EDITION');
        if ($override && in_array(strtolower($override), [self::EDITION_LITE, self::EDITION_PRO, self::EDITION_AGENCY], true)) {
            return strtolower($override);
        }
        if ($this->edition && $this->edition !== self::EDITION_LITE) {
            return $this->edition;
        }
        if (Craft::$app->getConfig()->getGeneral()->devMode) {
            return self::EDITION_PRO;
        }
        return $this->edition ?: self::EDITION_LITE;
    }

    /**
     * Check if Pro edition features are active.
     */
    public function hasPro(): bool
    {
        return in_array($this->getActiveEdition(), [self::EDITION_PRO, self::EDITION_AGENCY], true);
    }

    /**
     * Check if Agency edition features are active.
     */
    public function hasAgency(): bool
    {
        return $this->getActiveEdition() === self::EDITION_AGENCY;
    }

    /**
     * Registers the plugin components/services.
     */
    protected function registerComponents(): void
    {
        $this->setComponents([
            'audit' => AuditService::class,
            'score' => ScoreService::class,
            'seo' => SeoService::class,
            'ai' => AiService::class,
            'vector' => VectorService::class,
            'rag' => RagService::class,
        ]);
    }

    /**
     * Registers Control Panel asset bundle.
     */
    protected function registerCpAssets(): void
    {
        if (Craft::$app->getRequest()->getIsCpRequest()) {
            Craft::$app->getView()->registerAssetBundle(CpAsset::class);
        }
    }

    /**
     * Registers Twig extension for custom filters and functions.
     */
    protected function registerTwigExtensions(): void
    {
        Craft::$app->getView()->registerTwigExtension(new ContentIntelligenceTwigExtension());
    }

    /**
     * Registers craft.contentIntelligence variable for templates.
     */
    protected function registerVariables(): void
    {
        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            function(Event $event) {
                /** @var CraftVariable $variable */
                $variable = $event->sender;
                $variable->set('contentIntelligence', ContentIntelligenceVariable::class);
            }
        );
    }

    /**
     * Registers permissions with Craft CMS.
     */
    protected function registerPermissions(): void
    {
        Event::on(
            UserPermissions::class,
            UserPermissions::EVENT_REGISTER_PERMISSIONS,
            function(RegisterUserPermissionsEvent $event) {
                $event->permissions[] = [
                    'heading' => Craft::t('content-intelligence', 'Content Intelligence'),
                    'permissions' => [
                        'contentIntelligence:viewDashboard' => [
                            'label' => Craft::t('content-intelligence', 'View Dashboard'),
                        ],
                        'contentIntelligence:runAudits' => [
                            'label' => Craft::t('content-intelligence', 'Run Content & SEO Audits'),
                        ],
                        'contentIntelligence:useAi' => [
                            'label' => Craft::t('content-intelligence', 'Use AI Assistant (Pro/Agency)'),
                        ],
                        'contentIntelligence:manageKnowledgeBase' => [
                            'label' => Craft::t('content-intelligence', 'Manage Semantic Index & Knowledge Base (Agency)'),
                        ],
                        'contentIntelligence:askWebsite' => [
                            'label' => Craft::t('content-intelligence', 'Use Ask Your Website RAG (Agency)'),
                        ],
                        'contentIntelligence:manageSettings' => [
                            'label' => Craft::t('content-intelligence', 'Manage Plugin Settings'),
                        ],
                    ],
                ];
            }
        );
    }

    /**
     * Registers CP URL rules for Content Intelligence.
     */
    protected function registerCpRoutes(): void
    {
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function(RegisterUrlRulesEvent $event) {
                $event->rules['content-intelligence'] = 'content-intelligence/dashboard/index';
                $event->rules['content-intelligence/dashboard'] = 'content-intelligence/dashboard/index';
                $event->rules['content-intelligence/audit'] = 'content-intelligence/audit/index';
                $event->rules['content-intelligence/audit/run'] = 'content-intelligence/audit/run';
                $event->rules['content-intelligence/audit/export'] = 'content-intelligence/audit/export';
                $event->rules['content-intelligence/audit/audit-entry/<entryId:\d+>'] = 'content-intelligence/audit/audit-entry';
                $event->rules['content-intelligence/audit/<entryId:\d+>'] = 'content-intelligence/audit/view';
                $event->rules['content-intelligence/seo'] = 'content-intelligence/seo/index';
                $event->rules['content-intelligence/seo/run'] = 'content-intelligence/seo/run';
                $event->rules['content-intelligence/seo/<entryId:\d+>'] = 'content-intelligence/seo/view';
                $event->rules['content-intelligence/seo/analyze-ai'] = 'content-intelligence/seo/analyze-ai-seo';
                $event->rules['content-intelligence/ai'] = 'content-intelligence/ai/index';
                $event->rules['content-intelligence/ai/modal/<entryId:\d+>'] = 'content-intelligence/ai/sidebar-modal';
                $event->rules['content-intelligence/ai/test-connection'] = 'content-intelligence/ai/test-connection';
                $event->rules['content-intelligence/ai/generate-meta'] = 'content-intelligence/ai/generate-meta';
                $event->rules['content-intelligence/ai/improve-content'] = 'content-intelligence/ai/improve-content';
                $event->rules['content-intelligence/ai/generate-faqs'] = 'content-intelligence/ai/generate-faqs';
                $event->rules['content-intelligence/ai/generate-summary'] = 'content-intelligence/ai/generate-summary';
                $event->rules['content-intelligence/ai/generate-alt'] = 'content-intelligence/ai/generate-alt';
                $event->rules['content-intelligence/ai/analyze-seo'] = 'content-intelligence/ai/analyze-seo';
                $event->rules['content-intelligence/semantic'] = 'content-intelligence/semantic/index';
                $event->rules['content-intelligence/semantic/run'] = 'content-intelligence/semantic/run-index';
                $event->rules['content-intelligence/semantic/index-entry/<entryId:\d+>'] = 'content-intelligence/semantic/index-entry';
                $event->rules['content-intelligence/semantic/prune'] = 'content-intelligence/semantic/prune';
                $event->rules['content-intelligence/semantic/chunks/<entryId:\d+>'] = 'content-intelligence/semantic/view-chunks';
                $event->rules['content-intelligence/semantic/search'] = 'content-intelligence/semantic/search';
                $event->rules['content-intelligence/semantic/test-connection'] = 'content-intelligence/semantic/test-connection';
                $event->rules['content-intelligence/rag'] = 'content-intelligence/rag/index';
                $event->rules['content-intelligence/rag/ask'] = 'content-intelligence/rag/ask';
                $event->rules['content-intelligence/rag/related/<entryId:\d+>'] = 'content-intelligence/rag/related';
                $event->rules['content-intelligence/settings'] = 'content-intelligence/settings/index';
            }
        );
    }

    /**
     * Registers event listeners for Craft elements (e.g. entry save auto-audit and sidebar UI).
     */
    protected function registerElementEvents(): void
    {
        // Auto-audit on entry save
        Event::on(
            Entry::class,
            Entry::EVENT_AFTER_SAVE,
            function(Event $event) {
                /** @var Entry $entry */
                $entry = $event->sender;
                // Only audit actual section entries (skip revisions, drafts, and nested Matrix blocks)
                if ($entry->getIsRevision() || $entry->getIsDraft() || empty($entry->sectionId)) {
                    return;
                }
                $settings = $this->getSettings();
                if ($settings->enableAutoAuditOnSave) {
                    try {
                        $this->audit->auditEntry($entry);
                    } catch (\Throwable $e) {
                        Craft::error("Auto-audit error on entry save: {$e->getMessage()}", __METHOD__);
                    }
                }

                // Incremental auto-index on entry save (Agency Edition)
                if ($this->hasAgency()) {
                    try {
                        $this->vector->indexEntry($entry, $this->ai->isConfigured());
                    } catch (\Throwable $e) {
                        Craft::error("Auto-index error on entry save: {$e->getMessage()}", __METHOD__);
                    }
                }
            }
        );

        // Cleanup on entry delete
        Event::on(
            Entry::class,
            Entry::EVENT_AFTER_DELETE,
            function(Event $event) {
                /** @var Entry $entry */
                $entry = $event->sender;
                if ($entry && $entry->id) {
                    \abdulkadiragoliya\contentintelligence\records\AuditRecord::deleteAll(['entryId' => $entry->id]);
                    \abdulkadiragoliya\contentintelligence\records\EmbeddingRecord::deleteAll(['entryId' => $entry->id]);
                }
            }
        );

        // Sidebar Widget in Entry Editor
        Event::on(
            Entry::class,
            Element::EVENT_DEFINE_SIDEBAR_HTML,
            function(DefineHtmlEvent $event) {
                /** @var Entry $entry */
                $entry = $event->sender;
                if (!$entry->id || empty($entry->sectionId) || $entry->getIsRevision()) {
                    return;
                }

                $auditRecord = \abdulkadiragoliya\contentintelligence\records\AuditRecord::find()
                    ->where(['entryId' => $entry->id])
                    ->one();

                $view = Craft::$app->getView();
                $html = $view->renderTemplate('content-intelligence/_cp/sidebar-badge', [
                    'entry' => $entry,
                    'audit' => $auditRecord,
                    'isPro' => $this->hasPro(),
                    'aiConfigured' => $this->ai->isConfigured(),
                ]);

                $event->html .= $html;
            }
        );
    }

    /**
     * Registers template roots for CP views.
     */
    protected function registerTemplateRoots(): void
    {
        Event::on(
            View::class,
            View::EVENT_REGISTER_CP_TEMPLATE_ROOTS,
            function(RegisterTemplateRootsEvent $event) {
                $event->roots['content-intelligence'] = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'templates';
            }
        );
    }

    /**
     * @inheritdoc
     */
    public function getCpNavItem(): ?array
    {
        $item = parent::getCpNavItem();
        if (!$item) {
            return null;
        }

        $item['url'] = 'content-intelligence';

        $user = Craft::$app->getUser();
        if ($user->getIsGuest() || !$user->checkPermission('contentIntelligence:viewDashboard')) {
            return null;
        }

        $subnav = [
            'dashboard' => [
                'label' => Craft::t('content-intelligence', 'Dashboard'),
                'url' => 'content-intelligence',
            ],
            'audit' => [
                'label' => Craft::t('content-intelligence', 'Content Audit'),
                'url' => 'content-intelligence/audit',
            ],
            'seo' => [
                'label' => Craft::t('content-intelligence', 'SEO Audit'),
                'url' => 'content-intelligence/seo',
            ],
        ];

        // Pro & Agency feature navigation
        $subnav['ai'] = [
            'label' => Craft::t('content-intelligence', 'AI Assistant') . ($this->hasPro() ? '' : ' (Pro)'),
            'url' => 'content-intelligence/ai',
        ];

        // Agency feature navigation
        $subnav['semantic'] = [
            'label' => Craft::t('content-intelligence', 'Semantic Search') . ($this->hasAgency() ? '' : ' (Agency)'),
            'url' => 'content-intelligence/semantic',
        ];

        $subnav['rag'] = [
            'label' => Craft::t('content-intelligence', 'Ask Your Website') . ($this->hasAgency() ? '' : ' (Agency)'),
            'url' => 'content-intelligence/rag',
        ];

        if ($user->checkPermission('contentIntelligence:manageSettings')) {
            $subnav['settings'] = [
                'label' => Craft::t('content-intelligence', 'Settings'),
                'url' => 'content-intelligence/settings',
            ];
        }

        $item['subnav'] = $subnav;

        return $item;
    }

    /**
     * @inheritdoc
     */
    protected function createSettingsModel(): ?Model
    {
        return new Settings();
    }

    /**
     * @inheritdoc
     */
    public function getSettingsResponse(): mixed
    {
        return Craft::$app->getResponse()->redirect(UrlHelper::cpUrl('content-intelligence/settings'));
    }

    /**
     * @inheritdoc
     */
    protected function settingsHtml(): ?string
    {
        return Craft::$app->getView()->renderTemplate('content-intelligence/settings/index', [
            'settings' => $this->getSettings(),
            'edition' => $this->getActiveEdition(),
        ]);
    }
}
