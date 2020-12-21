<?php

namespace nystudio107\transcoder\variables;

use nystudio107\transcoder\helpers\Manifest as ManifestHelper;
use nystudio107\transcoder\assetbundles\transcoder\TranscoderAsset;

use Craft;
use craft\helpers\Template;

use yii\web\NotFoundHttpException;

use Twig\Markup;

class ManifestVariable
{
    // Protected Static Properties
    // =========================================================================

    protected static $config = [
        // If `devMode` is on, use webpack-dev-server to all for HMR (hot module reloading)
        'useDevServer' => true,
        // Manifest names
        'manifest'     => [
            'legacy' => 'manifest.json',
            'modern' => 'manifest.json',
        ],
        // Public server config
        'server'       => [
            'manifestPath' => '/',
            'publicPath' => '/',
        ],
        // webpack-dev-server config
        'devServer'    => [
            'manifestPath' => 'http://127.0.0.1:8080',
            'publicPath' => '/',
        ],
    ];

    // Public Methods
    // =========================================================================

    /**
     * ManifestVariable constructor.
     */
    public function __construct()
    {
        ManifestHelper::invalidateCaches();
        $bundle = new TranscoderAsset();
        $baseAssetsUrl = Craft::$app->assetManager->getPublishedUrl(
            $bundle->sourcePath,
            true
        );
        self::$config['server']['manifestPath'] = Craft::getAlias($bundle->sourcePath);
        self::$config['server']['publicPath'] = $baseAssetsUrl;
        $useDevServer = getenv('NYS_PLUGIN_DEVSERVER');
        if ($useDevServer !== false) {
            self::$config['useDevServer'] = (bool)$useDevServer;
        }
    }

    /**
     * @param string     $moduleName
     * @param bool       $async
     * @param null|array $config
     *
     * @return null|\Twig_Markup
     * @throws \yii\web\NotFoundHttpException
     */
    public function includeCssModule(string $moduleName, bool $async = false, $config = null)
    {
        return Template::raw(
            ManifestHelper::getCssModuleTags(self::$config, $moduleName, $async) ?? ''
        );
    }


    /**
     * Returns the uglified loadCSS rel=preload Polyfill as per:
     * https://github.com/filamentgroup/loadCSS#how-to-use-loadcss-recommended-example
     *
     * @return string
     */
    public static function includeCssRelPreloadPolyfill(): string
    {
        return Template::raw(
            ManifestHelper::getCssRelPreloadPolyfill() ?? ''
        );
    }

    /**
     * @param string     $moduleName
     * @param bool       $async
     * @param null|array $config
     *
     * @return null|\Twig_Markup
     * @throws \yii\web\NotFoundHttpException
     */
    public function includeJsModule(string $moduleName, bool $async = false, $config = null)
    {
        return Template::raw(
            ManifestHelper::getJsModuleTags(self::$config, $moduleName, $async) ?? ''
        );
    }

    /**
     * Return the URI to a module
     *
     * @param string $moduleName
     * @param string $type
     * @param null   $config
     *
     * @return null|\Twig_Markup
     * @throws \yii\web\NotFoundHttpException
     */
    public function getModuleUri(string $moduleName, string $type = 'modern', $config = null)
    {
        return Template::raw(
            ManifestHelper::getModule(self::$config, $moduleName, $type) ?? ''
        );
    }

    /**
     * Include the Safari 10.1 nomodule fix JavaScript
     *
     * @return \Twig_Markup
     */
    public function includeSafariNomoduleFix()
    {
        return Template::raw(
            ManifestHelper::getSafariNomoduleFix() ?? ''
        );
    }
}
