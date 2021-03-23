<?php
/**
 * Transcoder plugin for Craft CMS 3.x
 *
 * Transcode videos to various formats, and provide thumbnails of the video
 *
 * @link      https://nystudio107.com
 * @copyright Copyright (c) 2017 nystudio107
 */

namespace nystudio107\transcoder;

use nystudio107\transcoder\assetbundles\transcoder\TranscoderAsset;
use nystudio107\transcoder\services\Transcode;
use nystudio107\transcoder\variables\TranscoderVariable;
use nystudio107\transcoder\models\Settings;

use nystudio107\pluginmanifest\services\ManifestService;

use Craft;
use craft\base\Plugin;
use craft\console\Application as ConsoleApplication;
use craft\elements\Asset;
use craft\events\AssetThumbEvent;
use craft\events\PluginEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\events\RegisterCacheOptionsEvent;
use craft\helpers\Assets as AssetsHelper;
use craft\helpers\FileHelper;
use craft\helpers\UrlHelper;
use craft\services\Assets;
use craft\services\Plugins;
use craft\utilities\ClearCaches;
use craft\web\twig\variables\CraftVariable;
use craft\web\UrlManager;

use yii\base\ErrorException;
use yii\base\Event;

/**
 * Class Transcode
 *
 * @author    nystudio107
 * @package   Transcode
 * @since     1.0.0
 *
 * @property Transcode          $transcode
 * @property Settings           $settings
 * @property ManifestService    $manifest
 * @method   Settings   getSettings()
 */
class Transcoder extends Plugin
{
    // Static Properties
    // =========================================================================

    /**
     * @var Transcoder
     */
    public static $plugin;

    /**
     * @var Settings
     */
    public static $settings;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function init()
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
    public function clearAllCaches()
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
    protected function createSettingsModel()
    {
        return new Settings();
    }

    /**
     * Add in our Craft components
     */
    protected function addComponents()
    {
        // Register the manifest service
        $this->set('manifest', [
            'class' => ManifestService::class,
            'assetClass' => TranscoderAsset::class,
            'devServerManifestPath' => 'http://craft-transcoder-buildchain:8080/',
            'devServerPublicPath' => 'http://craft-transcoder-buildchain:8080/',
        ]);

        // Register our variables
        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            function (Event $event) {
                /** @var CraftVariable $variable */
                $variable = $event->sender;
                $variable->set('transcoder', [
                    'class' => TranscoderVariable::class,
                    'manifestService' => $this->manifest,
                ]);
            }
        );
    }

    /**
     * Install our event handlers
     */
    protected function installEventHandlers()
    {
        $settings = $this->getSettings();
        // Handler: Assets::EVENT_GET_THUMB_PATH
        Event::on(
            Assets::class,
            Assets::EVENT_GET_THUMB_PATH,
            function (AssetThumbEvent $event) {
                Craft::debug(
                    'Assets::EVENT_GET_THUMB_PATH',
                    __METHOD__
                );
                /** @var Asset $asset */
                $asset = $event->asset;
                if (AssetsHelper::getFileKindByExtension($asset->filename) === Asset::KIND_VIDEO) {
                    $path = Transcoder::$plugin->transcode->handleGetAssetThumbPath($event);
                    if (!empty($path)) {
                        $event->path = $path;
                    }
                }
            }
        );
        if ($settings->clearCaches) {
            // Add the Transcode path to the list of things the Clear Caches tool can delete.
            Event::on(
                ClearCaches::class,
                ClearCaches::EVENT_REGISTER_CACHE_OPTIONS,
                function (RegisterCacheOptionsEvent $event) {
                    $event->options[] = [
                        'key' => 'transcoder',
                        'label' => Craft::t('transcoder', 'Transcoder caches'),
                        'action' => [$this, 'clearAllCaches'],
                    ];
                }
            );
        }
        // Handler: Plugins::EVENT_AFTER_INSTALL_PLUGIN
        Event::on(
            Plugins::class,
            Plugins::EVENT_AFTER_INSTALL_PLUGIN,
            function (PluginEvent $event) {
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
    protected function installSiteEventListeners()
    {
        // Handler: UrlManager::EVENT_REGISTER_SITE_URL_RULES
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_SITE_URL_RULES,
            function (RegisterUrlRulesEvent $event) {
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
     * Return the custom frontend routes
     *
     * @return array
     */
    protected function customFrontendRoutes(): array
    {
        return [
            // Make webpack async bundle loading work out of published AssetBundles
            '/cpresources/transcoder/<resourceType:{handle}>/<fileName>' => 'transcoder/manifest/resource',
        ];
    }
}
