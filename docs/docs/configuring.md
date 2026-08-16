---
title: Configuring Transcoder
description: Configuring Transcoder documentation for the Transcoder plugin. The Transcoder plugin allows you to transcode video & audio files to various formats, and provide video thumbnails
---
# Configuring Transcoder

Configure the media workflow from Transcoder’s Craft control-panel settings or with a `craft/config/transcoder.php` file. Don’t edit the plugin’s bundled `config.php`; copy it to `craft/config` when settings should be managed in code.

The media queue and Video watermark tabs use Craft autosuggest fields for values that can come from environment variables. The watermark path also suggests Yii aliases. Numeric environment variables must resolve to integers within the same limits shown by their literal values.

You will also need [ffmpeg](https://ffmpeg.org/) installed for Transcoder to work. On Ubuntu 16.04, you can do just:

```bash
    sudo apt-get update
    sudo apt-get install ffmpeg
```

To install `ffmpeg` on Centos 6/7, you can follow the guide [How to Install FFmpeg on CentOS](https://www.vultr.com/docs/how-to-install-ffmpeg-on-centos)

If you have managed hosting, contact your sysadmin to get `ffmpeg` installed.

## Video queue and filenames

Video uploads can be encoded by Craft’s queue instead of starting ffmpeg during a web request:

```php
return [
    'queueVideosOnAssetUpload' => true,
    'videoQueueDelaySeconds' => 5,
    'queuedVideoOptions' => [
        'videoBitRate' => '1200k',
        'videoFrameRate' => 30,
        'width' => 1280,
        'height' => 720,
    ],
];
```

A working Craft queue runner is required. The queue job waits for ffmpeg to finish, so Craft can report failures and retry the job through the configured queue driver.

## GIF queue

GIF uploads can use the same failure-visible Craft queue workflow without changing `getGifUrl()` or its string return value:

```php
return [
    'queueGifsOnAssetUpload' => true,
    'gifQueueDelaySeconds' => 5,
    'queuedGifOptions' => [
        'videoEncoder' => 'gif',
    ],
];
```

GIF queueing is disabled by default. The queue worker runs ffmpeg synchronously and only completes after a non-empty output has been created. Existing Twig calls keep their original on-demand behavior.

## Audio queue

Audio uploads can also be encoded by Craft’s queue without changing `getAudioUrl()` or its string return value:

```php
return [
    'queueAudioOnAssetUpload' => true,
    'audioQueueDelaySeconds' => 5,
    'queuedAudioOptions' => [
        'audioEncoder' => 'mp3',
        'audioBitRate' => '128k',
        'audioSampleRate' => '44100',
        'audioChannels' => '2',
    ],
];
```

Audio queueing is disabled by default. The job overrides the internal `synchronous` option so Craft only marks it complete after ffmpeg exits successfully and creates a non-empty output. Existing Twig calls and configured filenames are unchanged.

`videoFilenameStrategy` supports:

- `options` (default): preserves Transcoder’s original parameterized filenames. Output-affecting options such as `videoBitRate` remain part of the filename.
- `source`: uses the source asset name plus the encoder suffix, producing one stable output name per source asset.

### Output subfolders

Passing the actual Craft `Asset` to video and thumbnail helpers is preferred. With `createSubfolders` enabled, Transcoder uses Craft's Asset folder for both the generated filesystem path and public URL. Automatic Control Panel thumbnails wait until an uploaded Asset has reached its final folder.

When an integration can only pass a string URL or path, configure `subfolderUrlSegment` with the one-based path segment that contains the desired output folder. For example, segment `3` extracts `197915` from `/content/videos/197915/video.mp4`:

```php
return [
    'createSubfolders' => true,
    'subfolderUrlSegment' => 3,
];
```

Leave `subfolderUrlSegment` as `false` (the default) when string inputs should use their base output directories. Transcoder normalizes path and URL separators, so configured video and thumbnail paths do not depend on a trailing slash.

## Watermarks

Watermarking accepts a local path, Yii alias, environment value, or public image URL:

```php
return [
    'enableVideoWatermark' => true,
    'videoWatermarkPath' => '@webroot/assets/watermark.png',
    'videoWatermarkWidth' => 180,
    'videoWatermarkPosition' => 'bottom-right',
    'videoWatermarkPadding' => 24,
    'videoWatermarkOpacity' => 80,
];
```

Watermark settings are included in option-based output filenames through a short fingerprint. Changing the watermark therefore creates a new option-based output instead of reusing an incompatible encode.

## Video posters

Configured poster formats can be generated after a queued video encode or through their own upload job:

```php
return [
    'enableVideoPosters' => true,
    'queueVideoPostersOnAssetUpload' => true,
    // Shared with uploaded video encoding jobs.
    'videoQueueDelaySeconds' => 5,
    'preventVideoPosterBlackBars' => true,
    'videoPosterFormats' => [
        '16_9' => [
            'width' => 800,
            'height' => 450,
            'timeInSecs' => 3,
        ],
        'square' => [
            'width' => 800,
            'height' => 800,
            'timeInSecs' => 1,
        ],
    ],
];
```

`queueVideoPostersOnAssetUpload` is disabled by default. When full video upload encoding is enabled, its existing `EncodeVideo` job remains responsible for poster generation and no duplicate poster job is queued. When full video upload encoding is disabled, this setting queues only the configured poster formats. The job reloads the Asset by ID and fails visibly instead of writing into Craft's temporary upload folder.

When `preventVideoPosterBlackBars` is enabled, the video frame is fitted over a blurred cover version of the same frame. Poster timestamps are clamped to the source duration for short videos.

Brought to you by [nystudio107](https://nystudio107.com)
