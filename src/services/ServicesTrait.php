<?php
/**
 * Transcoder plugin for Craft CMS
 *
 * Transcode videos to various formats, and provide thumbnails of the video
 *
 * @link      https://nystudio107.com
 * @copyright Copyright (c) 2022 nystudio107
 */

namespace nystudio107\transcoder\services;

use craft\helpers\ArrayHelper;
use nystudio107\pluginvite\services\VitePluginService;
use nystudio107\transcoder\assetbundles\transcoder\TranscoderAsset;
use yii\base\InvalidConfigException;

/**
 * @author    nystudio107
 * @package   Transcode
 * @since     1.2.23
 *
 * @property Transcode $transcode
 * @property VitePluginService $vite
 */
trait ServicesTrait
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function __construct($id, $parent = null, array $config = [])
    {
        // Constants aren't allowed in traits until PHP >= 8.2
        $majorVersion = '3';
        // Dev server container name & port are based on the major version of this plugin
        $devPort = 3000 + (int)$majorVersion;
        $versionName = 'v' . $majorVersion;
        // Merge in the passed config, so it our config can be overridden by Plugins::pluginConfigs['vite']
        // ref: https://github.com/craftcms/cms/issues/1989
        $config = ArrayHelper::merge([
            'components' => [
                'transcode' => Transcode::class,
                // Register the vite service
                'vite' => [
                    'class' => VitePluginService::class,
                    'assetClass' => TranscoderAsset::class,
                    'useDevServer' => true,
                    'devServerInternal' => 'http://craft-transcoder-' . $versionName . '-buildchain-dev:' . $devPort,
                    'devServerPublic' => 'http://localhost:' . $devPort,
                    'errorEntry' => 'src/js/app.ts',
                    'checkDevServer' => true,
                ],
            ],
        ], $config);

        parent::__construct($id, $parent, $config);
    }

    /**
     * Returns the transcode service
     *
     * @return Transcode The transcode service
     * @throws InvalidConfigException
     */
    public function getTranscode(): Transcode
    {
        return $this->get('transcode');
    }

    /**
     * Returns the vite service
     *
     * @return VitePluginService The vite service
     * @throws InvalidConfigException
     */
    public function getVite(): VitePluginService
    {
        return $this->get('vite');
    }
}
