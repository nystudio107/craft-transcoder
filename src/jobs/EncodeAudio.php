<?php

namespace nystudio107\transcoder\jobs;

use Craft;
use craft\elements\Asset;
use craft\helpers\Assets as AssetsHelper;
use craft\queue\BaseJob;
use nystudio107\transcoder\Transcoder;
use RuntimeException;

/**
 * Encodes an uploaded audio Asset inside Craft's queue worker.
 */
class EncodeAudio extends BaseJob
{
    public ?int $assetId = null;

    public array $audioOptions = [];

    /**
     * @inheritdoc
     */
    public function execute($queue): void
    {
        $asset = Asset::find()->id($this->assetId)->one();
        if (!$asset instanceof Asset
            || AssetsHelper::getFileKindByExtension($asset->filename) !== Asset::KIND_AUDIO
        ) {
            throw new RuntimeException("Unable to find audio asset #{$this->assetId}.");
        }

        Craft::info("Starting queued audio encoding for asset #{$this->assetId}.", __METHOD__);
        $executed = Transcoder::$plugin->transcode->runAudioAssetWork($asset, function() use ($asset): void {
            $audioOptions = array_merge($this->audioOptions, ['synchronous' => true]);
            $url = Transcoder::$plugin->transcode->getAudioUrl($asset, $audioOptions);
            if ($url === '') {
                throw new RuntimeException("Audio encoding failed for asset #{$this->assetId}.");
            }

            Craft::info("Encoded audio asset #{$this->assetId}: $url", __METHOD__);
        });

        if (!$executed) {
            throw new RuntimeException("Audio asset #{$this->assetId} is already being processed.");
        }
    }

    /**
     * @inheritdoc
     */
    protected function defaultDescription(): ?string
    {
        return "Encoding audio asset #{$this->assetId}";
    }
}
