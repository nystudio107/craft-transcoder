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

use nystudio107\transcoder\variables\TranscoderVariable;

use Craft;
use craft\base\Plugin;
use craft\services\Plugins;
use craft\events\PluginEvent;
use craft\web\UrlManager;
use craft\events\RegisterUrlRulesEvent;
use yii\base\Event;

/**
 * @author    nystudio107
 * @package   Transcoder
 * @since     1.0.0
 */
class Transcoder extends Plugin
{
    // Static Properties
    // =========================================================================

    /**
     * @var static
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

        Craft::info('Transcoder ' . Craft::t('transcoder', 'plugin loaded'), __METHOD__);
    }

    /**
     * @inheritdoc
     */
    public function defineTemplateComponent()
    {
        return TranscoderVariable::class;
    }

}
