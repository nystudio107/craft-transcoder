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

use nystudio107\transcoder\services\Transcode;
use nystudio107\transcoder\variables\TranscoderVariable;
use nystudio107\transcoder\models\Settings;

use Craft;
use craft\base\Plugin;
use craft\console\Application as ConsoleApplication;
use craft\events\RegisterCacheOptionsEvent;
use craft\utilities\ClearCaches;
use craft\web\twig\variables\CraftVariable;

use yii\base\Event;

/**
 * Class Transcode
 *
 * @author    nystudio107
 * @package   Transcode
 * @since     1.0.0
 *
 * @property  Transcode $transcode
 */
class Transcoder extends Plugin
{
    // Static Properties
    // =========================================================================

    /**
     * @var Transcoder
     */
    public static $plugin;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
        self::$plugin = $this;

        // Register our variables
        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            function (Event $event) {
                /** @var CraftVariable $variable */
                $variable = $event->sender;
                $variable->set('transcoder', TranscoderVariable::class);
            }
        );

        // Handle console commands
        if (Craft::$app instanceof ConsoleApplication) {
            $this->controllerNamespace = 'nystudio107\transcoder\console\controllers';
        }

        // Add the Transcode path to the list of things the Clear Caches tool can delete.
        Event::on(
            ClearCaches::className(),
            ClearCaches::EVENT_REGISTER_CACHE_OPTIONS,
            function (RegisterCacheOptionsEvent $event) {
                $event->options[] = [
                    'key' => 'transcoder',
                    'label' => Craft::t('transcoder', 'Transcoder caches'),
                    'action' => Transcoder::$plugin->getSettings()->transcoderPath,
                ];
            }
        );

        Craft::info(
            Craft::t(
                'transcoder',
                '{name} plugin loaded',
                ['name' => $this->name]
            ),
            __METHOD__
        );
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
}
