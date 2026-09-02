<?php
/**
 * Transcoder plugin for Craft CMS
 *
 * Transcode videos to various formats, and provide thumbnails of the video
 *
 * @link      https://nystudio107.com
 * @copyright Copyright (c) 2017 nystudio107
 */

namespace nystudio107\transcoder;

use Craft;
use craft\base\Model;
use craft\base\Plugin;
use craft\console\Application as ConsoleApplication;
use craft\elements\Asset;
use craft\events\DefineAssetThumbUrlEvent;
use craft\events\ModelEvent;
use craft\events\PluginEvent;
use craft\events\RegisterCacheOptionsEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\events\TemplateEvent;
use craft\helpers\App;
use craft\helpers\Assets as AssetsHelper;
use craft\helpers\FileHelper;
use craft\helpers\UrlHelper;
use craft\services\Assets;
use craft\services\Plugins;
use craft\utilities\ClearCaches;
use craft\web\twig\variables\CraftVariable;
use craft\web\UrlManager;
use craft\web\View;
use nystudio107\transcoder\jobs\EncodeAudio;
use nystudio107\transcoder\jobs\EncodeGif;
use nystudio107\transcoder\jobs\EncodeVideo;
use nystudio107\transcoder\jobs\GenerateVideoPosters;
use nystudio107\transcoder\models\Settings;
use nystudio107\transcoder\services\ServicesTrait;
use nystudio107\transcoder\variables\TranscoderVariable;
use yii\base\ErrorException;
use yii\base\Event;

/**
 * Class Transcode
 *
 * @author    nystudio107
 * @package   Transcode
 * @since     1.0.0
 * @method Settings getSettings()
 */
class Transcoder extends Plugin
{
    // Traits
    // =========================================================================

    use ServicesTrait;

    // Constants
    // =========================================================================

    private const SETTINGS_TAB_FIELDS = [
        'settings-tab-video-queue' => [
            'queueVideosOnAssetUpload',
            'videoQueueDelaySeconds',
            'videoEncodeMaxRetries',
            'videoEncodeRetryDelaySeconds',
            'queuedVideoOptions',
            'queueGifsOnAssetUpload',
            'gifQueueDelaySeconds',
            'queuedGifOptions',
            'queueAudioOnAssetUpload',
            'audioQueueDelaySeconds',
            'queuedAudioOptions',
            'videoFilenameStrategy',
            'subfolderUrlSegment',
        ],
        'settings-tab-video-posters' => [
            'enableVideoPosters',
            'queueVideoPostersOnAssetUpload',
            'preventVideoPosterBlackBars',
            'videoPosterFormats',
        ],
        'settings-tab-video-watermark' => [
            'enableVideoWatermark',
            'videoWatermarkPath',
            'videoWatermarkWidth',
            'videoWatermarkPosition',
            'videoWatermarkPadding',
            'videoWatermarkOpacity',
        ],
    ];

    // Static Properties
    // =========================================================================

    /**
     * @var null|Transcoder
     */
    public static ?Transcoder $plugin;

    /**
     * @var null|Settings
     */
    public static ?Settings $settings;

    /** @var array<int, true> Assets waiting for Craft to move them out of temporary upload storage. */
    private array $pendingTemporaryUploads = [];

    // Public Properties
    // =========================================================================

    /**
     * @var bool
     */
    public bool $hasCpSection = false;

    /**
     * @var bool
     */
    public bool $hasCpSettings = true;

    /**
     * @var string
     */
    public string $schemaVersion = '1.0.0';

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function init(): void
    {
        parent::init();
        self::$plugin = $this;
        // Initialize properties
        self::$settings = self::$plugin->getSettings();
        // Handle console commands
        if (Craft::$app instanceof ConsoleApplication) {
            $this->controllerNamespace = 'nystudio107\transcoder\console\controllers';
        }
        // Add in our Craft components
        $this->addComponents();
        // Install our global event handlers
        $this->installEventHandlers();
        // Register settings page tabs
        $this->registerSettingsTabs();
        // We've loaded!
        Craft::info(
            Craft::t(
                'transcoder',
                '{name} plugin loaded',
                ['name' => $this->name]
            ),
            __METHOD__
        );
    }

    /**
     * Clear all the caches!
     */
    public function clearAllCaches(): void
    {
        $transcoderPaths = self::$plugin->getSettings()->transcoderPaths;

        foreach ($transcoderPaths as $key => $value) {
            $dir = Craft::parseEnv($value);
            try {
                FileHelper::clearDirectory($dir);
                Craft::info(
                    Craft::t(
                        'transcoder',
                        '{name} cache directory cleared',
                        ['name' => $key]
                    ),
                    __METHOD__
                );
            } catch (ErrorException $e) {
                // the directory doesn't exist
                Craft::error($e->getMessage(), __METHOD__);
            }
        }
    }

    // Protected Methods
    // =========================================================================

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
    protected function settingsHtml(): ?string
    {
        $settings = $this->getSettings();

        return Craft::$app->getView()->renderTemplate('transcoder/settings', [
            'settings' => $settings,
            'selectedTab' => $this->firstSettingsErrorTab($settings) ?? array_key_first(self::SETTINGS_TAB_FIELDS),
        ]);
    }

    /**
     * Register Craft CP tabs for the plugin settings page.
     */
    protected function registerSettingsTabs(): void
    {
        Event::on(
            View::class,
            View::EVENT_BEFORE_RENDER_TEMPLATE,
            function(TemplateEvent $event) {
                if (
                    $event->template === 'settings/plugins/_settings.twig'
                    && ($event->variables['plugin']->handle ?? null) === $this->handle
                ) {
                    $settings = $this->getSettings();
                    $tabs = [
                        'settings-tab-video-queue' => [
                            'label' => Craft::t('transcoder', 'Video queue'),
                            'url' => '#settings-tab-video-queue',
                        ],
                        'settings-tab-video-posters' => [
                            'label' => Craft::t('transcoder', 'Video posters'),
                            'url' => '#settings-tab-video-posters',
                        ],
                        'settings-tab-video-watermark' => [
                            'label' => Craft::t('transcoder', 'Video watermark'),
                            'url' => '#settings-tab-video-watermark',
                        ],
                    ];

                    foreach (self::SETTINGS_TAB_FIELDS as $tabId => $fields) {
                        if ($this->settingsFieldsHaveErrors($settings, $fields)) {
                            $tabs[$tabId]['class'] = ['error'];
                        }
                    }

                    $event->variables['tabs'] = $tabs;
                    $selectedTab = $this->firstSettingsErrorTab($settings);
                    if ($selectedTab !== null) {
                        $event->variables['selectedTab'] = $selectedTab;
                    }
                }
            }
        );
    }

    /**
     * Return the first settings tab containing validation errors.
     */
    private function firstSettingsErrorTab(Settings $settings): ?string
    {
        foreach (self::SETTINGS_TAB_FIELDS as $tabId => $fields) {
            if ($this->settingsFieldsHaveErrors($settings, $fields)) {
                return $tabId;
            }
        }

        return null;
    }

    /**
     * Return whether any of the given settings fields contain validation errors.
     *
     * @param string[] $fields
     */
    private function settingsFieldsHaveErrors(Settings $settings, array $fields): bool
    {
        foreach ($fields as $field) {
            if ($settings->hasErrors($field)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Add in our Craft components
     */
    protected function addComponents(): void
    {
        // Register our variables
        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            function(Event $event) {
                /** @var CraftVariable $variable */
                $variable = $event->sender;
                $variable->set('transcoder', [
                    'class' => TranscoderVariable::class,
                    'viteService' => $this->vite,
                ]);
            }
        );
    }

    /**
     * Install our event handlers
     */
    protected function installEventHandlers(): void
    {
        $settings = $this->getSettings();
        // Handler: Assets::EVENT_GET_THUMB_PATH
        Event::on(
            Assets::class,
            Assets::EVENT_DEFINE_THUMB_URL,
            static function(DefineAssetThumbUrlEvent $event) {
                Craft::debug(
                    'Assets::EVENT_GET_THUMB_PATH',
                    __METHOD__
                );
                $asset = $event->asset;
                if (AssetsHelper::getFileKindByExtension($asset->filename) === Asset::KIND_VIDEO) {
                    $path = Transcoder::$plugin->transcode->handleGetAssetThumbPath($event);
                    if (!empty($path)) {
                        $event->url = $path;
                    }
                }
            }
        );
        if ($settings->clearCaches) {
            // Add the Transcoded path to the list of things the Clear Caches tool can delete.
            Event::on(
                ClearCaches::class,
                ClearCaches::EVENT_REGISTER_CACHE_OPTIONS,
                function(RegisterCacheOptionsEvent $event) {
                    $event->options[] = [
                        'key' => 'transcoder',
                        'label' => Craft::t('transcoder', 'Transcoder caches'),
                        'action' => [$this, 'clearAllCaches'],
                    ];
                }
            );
        }
        if ($settings->queueVideosOnAssetUpload
            || $settings->queueVideoPostersOnAssetUpload
            || $settings->queueGifsOnAssetUpload
            || $settings->queueAudioOnAssetUpload
        ) {
            Event::on(
                Asset::class,
                Asset::EVENT_AFTER_SAVE,
                function(ModelEvent $event) use ($settings) {
                    $asset = $event->sender;
                    if (!$asset instanceof Asset || !$asset->id) {
                        return;
                    }

                    $assetId = (int)$asset->id;
                    if ($event->isNew && $this->transcode->isTemporaryUploadAsset($asset)) {
                        $this->pendingTemporaryUploads[$assetId] = true;
                        return;
                    }

                    if (!$event->isNew) {
                        if (!isset($this->pendingTemporaryUploads[$assetId])) {
                            return;
                        }
                        if ($this->transcode->isTemporaryUploadAsset($asset)) {
                            return;
                        }
                        unset($this->pendingTemporaryUploads[$assetId]);
                    }

                    $isGif = strtolower(pathinfo($asset->filename, PATHINFO_EXTENSION)) === 'gif';
                    $kind = AssetsHelper::getFileKindByExtension($asset->filename);
                    if ($isGif && $settings->queueGifsOnAssetUpload) {
                        $this->queueUploadedMedia(
                            new EncodeGif([
                                'assetId' => $asset->id,
                                'gifOptions' => $settings->queuedGifOptions,
                            ]),
                            $settings->gifQueueDelaySeconds,
                            'GIF',
                            $assetId
                        );
                        return;
                    }

                    if ($kind === Asset::KIND_AUDIO && $settings->queueAudioOnAssetUpload) {
                        $this->queueUploadedMedia(
                            new EncodeAudio([
                                'assetId' => $asset->id,
                                'audioOptions' => $settings->queuedAudioOptions,
                            ]),
                            $settings->audioQueueDelaySeconds,
                            'audio',
                            $assetId
                        );
                        return;
                    }

                    if ($kind === Asset::KIND_VIDEO) {
                        if ($settings->queueVideosOnAssetUpload) {
                            $this->queueUploadedMedia(
                                new EncodeVideo([
                                    'assetId' => $asset->id,
                                    'videoOptions' => $settings->queuedVideoOptions,
                                ]),
                                $settings->videoQueueDelaySeconds,
                                'video',
                                $assetId
                            );
                            return;
                        }

                        if ($this->transcode->shouldQueueStandaloneVideoPostersOnUpload()) {
                            $this->queueUploadedMedia(
                                new GenerateVideoPosters([
                                    'assetId' => $asset->id,
                                ]),
                                $settings->videoQueueDelaySeconds,
                                'video poster',
                                $assetId
                            );
                        }
                    }
                }
            );
        }
        // Handler: Plugins::EVENT_AFTER_INSTALL_PLUGIN
        Event::on(
            Plugins::class,
            Plugins::EVENT_AFTER_INSTALL_PLUGIN,
            function(PluginEvent $event) {
                if ($event->plugin === $this) {
                    $request = Craft::$app->getRequest();
                    if ($request->isCpRequest) {
                        Craft::$app->getResponse()->redirect(UrlHelper::cpUrl('transcoder/welcome'))->send();
                    }
                }
            }
        );
        $request = Craft::$app->getRequest();
        // Install only for non-console site requests
        if ($request->getIsSiteRequest() && !$request->getIsConsoleRequest()) {
            $this->installSiteEventListeners();
        }
    }

    /**
     * Install site event listeners for site requests only
     */
    protected function installSiteEventListeners(): void
    {
        // Handler: UrlManager::EVENT_REGISTER_SITE_URL_RULES
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_SITE_URL_RULES,
            function(RegisterUrlRulesEvent $event) {
                Craft::debug(
                    'UrlManager::EVENT_REGISTER_SITE_URL_RULES',
                    __METHOD__
                );
                // Register our Control Panel routes
                $event->rules = array_merge(
                    $event->rules,
                    $this->customFrontendRoutes()
                );
            }
        );
    }

    /**
     * Push an uploaded media job with its configured delay.
     */
    protected function queueUploadedMedia(object $job, int|string $delaySeconds, string $mediaType, int $assetId): void
    {
        $queue = Craft::$app->getQueue();
        $queueDelay = max(0, (int)App::parseEnv((string)$delaySeconds));
        if ($queueDelay > 0) {
            $queue = $queue->delay($queueDelay);
        }

        $jobId = $queue->push($job);
        if ($jobId === null) {
            Craft::error("Unable to queue $mediaType asset #$assetId for encoding.", __METHOD__);
            return;
        }

        Craft::info("Queued $mediaType asset #$assetId for encoding; job ID: $jobId", __METHOD__);
    }

    /**
     * Return the custom frontend routes
     *
     * @return array
     */
    protected function customFrontendRoutes(): array
    {
        return [
        ];
    }
}
