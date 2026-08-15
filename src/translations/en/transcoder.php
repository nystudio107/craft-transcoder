<?php
/**
 * Transcoder plugin for Craft CMS
 *
 * Transcode videos to various formats, and provide thumbnails of the video
 *
 * @link      https://nystudio107.com
 * @copyright Copyright (c) 2017 nystudio107
 */

/**
 * @author    nystudio107
 * @package   Transcode
 * @since     1.0.0
 */
return [
    'Transcoder caches' => 'Transcoder caches',
    'Video queue' => 'Video queue',
    'Queue videos on asset upload' => 'Queue videos on asset upload',
    'Add newly uploaded video assets to Craft’s queue for background encoding.' => 'Add newly uploaded video assets to Craft’s queue for background encoding.',
    'Video queue delay' => 'Video queue delay',
    'Seconds to wait before an uploaded video starts encoding.' => 'Seconds to wait before an uploaded video starts encoding.',
    'GIF queue' => 'GIF queue',
    'Queue GIFs on asset upload' => 'Queue GIFs on asset upload',
    'Add newly uploaded GIF assets to Craft’s queue for background encoding.' => 'Add newly uploaded GIF assets to Craft’s queue for background encoding.',
    'GIF queue delay' => 'GIF queue delay',
    'Seconds to wait before an uploaded GIF starts encoding.' => 'Seconds to wait before an uploaded GIF starts encoding.',
    'Video filename strategy' => 'Video filename strategy',
    'Use the original option-based filename, or keep one stable filename per source asset.' => 'Use the original option-based filename, or keep one stable filename per source asset.',
    'Encoding options' => 'Encoding options',
    'Source asset' => 'Source asset',
    'URL subfolder segment' => 'URL subfolder segment',
    'For string URL/path inputs, use this one-based path segment as the output subfolder. Leave empty to disable it.' => 'For string URL/path inputs, use this one-based path segment as the output subfolder. Leave empty to disable it.',
    'Video watermark' => 'Video watermark',
    'Enable video watermark' => 'Enable video watermark',
    'Overlay the configured image on newly encoded videos.' => 'Overlay the configured image on newly encoded videos.',
    'Watermark path or URL' => 'Watermark path or URL',
    'Use a local path, Yii alias, environment value, or public image URL.' => 'Use a local path, Yii alias, environment value, or public image URL.',
    'Watermark width' => 'Watermark width',
    'Optional width in pixels. Leave empty to keep the original size.' => 'Optional width in pixels. Leave empty to keep the original size.',
    'Watermark position' => 'Watermark position',
    'Top left' => 'Top left',
    'Top right' => 'Top right',
    'Bottom left' => 'Bottom left',
    'Bottom right' => 'Bottom right',
    'Watermark padding' => 'Watermark padding',
    'Watermark opacity' => 'Watermark opacity',
    'Percentage from 0 to 100.' => 'Percentage from 0 to 100.',
    'Video posters' => 'Video posters',
    'Generate video posters' => 'Generate video posters',
    'Generate the configured poster images after a queued video is encoded.' => 'Generate the configured poster images after a queued video is encoded.',
    'Prevent poster black bars' => 'Prevent poster black bars',
    'Fill unused poster space with a blurred cover image behind the fitted video frame.' => 'Fill unused poster space with a blurred cover image behind the fitted video frame.',
    'Video poster formats' => 'Video poster formats',
    'Handle' => 'Handle',
    'Width' => 'Width',
    'Height' => 'Height',
    'Time in seconds' => 'Time in seconds',
    'Refreshing video asset #{id}' => 'Refreshing video asset #{id}',
    'Waiting for current video encoding to finish' => 'Waiting for current video encoding to finish',
    'Video refresh complete' => 'Video refresh complete',
    '{name} plugin loaded' => '{name} plugin loaded',
    '{name} cache directory cleared' => '{name} cache directory cleared',
    'Manifest file not found at: {manifestPath}' => 'Manifest file not found at: {manifestPath}',
    'Module does not exist in the manifest: {moduleName}' => 'Module does not exist in the manifest: {moduleName}',
];
