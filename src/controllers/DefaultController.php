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

use nystudio107\transcoder\Transcoder;

use Craft;
use craft\web\Controller;

/**
 * @author    nystudio107
 * @package   Transcoder
 * @since     1.0.0
 */
class DefaultController extends Controller
{

    // Protected Properties
    // =========================================================================

    /**
     * @var    bool|array Allows anonymous access to this controller's actions.
     *         The actions must be in 'kebab-case'
     * @access protected
     */
    protected $allowAnonymous = ['download-file'];

    // Public Methods
    // =========================================================================

    /**
     * Force the download of a given $url.  We do it this way to prevent people
     * from downloading things that are outside of the server root.
     */
    public function actionDownloadFile()
    {
        $url = urldecode(Craft::$app->getRequest()->getParam('url'));
        $filePath = parse_url($url, PHP_URL_PATH);
        $filePath = $_SERVER['DOCUMENT_ROOT'] . $filePath;
        Craft::$app->getResponse()->sendFile(
            $filePath,
            null,
            ['inline' => false]
        );
        Craft::$app->end();
    }
}
