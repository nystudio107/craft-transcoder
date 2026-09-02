<?php

namespace nystudio107\transcoder\jobs;

use Craft;
use craft\elements\Asset;
use craft\helpers\Assets as AssetsHelper;
use craft\queue\BaseJob;
use nystudio107\transcoder\Transcoder;
use RuntimeException;

/**
 * Generates configured poster formats for an uploaded video Asset.
 */
class GenerateVideoPosters extends BaseJob
{
    public ?int $assetId = null;

    /**
     * @inheritdoc
     */
    public function execute($queue): void
    {
        $asset = Asset::find()->id($this->assetId)->one();
        if (!$asset instanceof Asset
            || AssetsHelper::getFileKindByExtension($asset->filename) !== Asset::KIND_VIDEO
        ) {
            throw new RuntimeException("Unable to find video asset #{$this->assetId} for poster generation.");
        }

        if (!Transcoder::$plugin->getSettings()->enableVideoPosters) {
            Craft::info("Skipped disabled video poster generation for asset #{$this->assetId}.", __METHOD__);
            return;
        }

        if (Transcoder::$plugin->transcode->isTemporaryUploadAsset($asset)) {
            throw new RuntimeException("Video asset #{$this->assetId} is still in temporary upload storage.");
        }

        Craft::info("Starting queued video poster generation for asset #{$this->assetId}.", __METHOD__);
        $executed = Transcoder::$plugin->transcode->runVideoAssetWork($asset, function() use ($asset): void {
            $posters = Transcoder::$plugin->transcode->generateVideoPosters($asset);
            if (in_array('', $posters, true)) {
                throw new RuntimeException("Video poster generation failed for asset #{$this->assetId}.");
            }

            Craft::info(
                'Generated ' . count($posters) . " video poster(s) for asset #{$this->assetId}.",
                __METHOD__
            );
        });

        if (!$executed) {
            throw new RuntimeException("Video asset #{$this->assetId} is already being processed.");
        }
    }

    /**
     * @inheritdoc
     */
    protected function defaultDescription(): ?string
    {
        return "Generating video posters for asset #{$this->assetId}";
    }
}
