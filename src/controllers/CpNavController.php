<?php
/**
 * Transcoder plugin for Craft CMS 3.x
 *
 * Transcode videos to various formats, and provide thumbnails of the video
 *
 * @link      https://nystudio107.com
 * @copyright Copyright (c) 2017 nystudio107
 */

namespace nystudio107\transcoder\controllers;

use Craft;
use craft\web\Controller;

use yii\web\Response;

/**
 * @author    nystudio107
 * @package   Transcode
 * @since     1.0.0
 */
class CpNavController extends Controller
{
    // Constants
    // =========================================================================

    // Protected Properties
    // =========================================================================

    /**
     * @var    bool|array Allows anonymous access to this controller's actions.
     *         The actions must be in 'kebab-case'
     * @access protected
     */
    protected $allowAnonymous = [
        'resource'
    ];

    // Public Methods
    // =========================================================================

    /**
     * Make webpack async bundle loading work out of published AssetBundles
     *
     * @param string $resourceType
     * @param string $fileName
     *
     * @return Response
     */
    public function actionResource(string $resourceType = '', string $fileName = ''): Response
    {
        $baseAssetsUrl = Craft::$app->assetManager->getPublishedUrl(
            '@nystudio107/transcoder/assetbundles/transcoder/dist',
            true
        );
        $url = "{$baseAssetsUrl}/{$resourceType}/{$fileName}";

        return $this->redirect($url);
    }
}
