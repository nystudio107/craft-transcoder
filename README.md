# Transcoder plugin for Craft CMS 3.x

Transcode videos to various formats, and provide thumbnails of the video

Related: [Transcoder for Craft 2.x](https://github.com/nystudio107/transcoder)

## Installation

To install Transcoder, follow these steps:

3. Install with Composer via `composer require nystudio107/craft3-transcoder`
4. Install plugin in the Craft Control Panel under Settings > Plugins

Transcoder works on Craft 3.x.

## Transcoder Overview

The Transcoder video allows you to take any locally stored video, and transcode it into any bitrate/framerate, and save it out as a web-ready `.mp4` file.

It also allows you to get a thumbnail of the video in any size and at any timecode.

Finally, it lets you download an arbitrary file (such as the transcoded video) via a special download link.

If the source file has changed since the last time the video was encoded, it will re-encode the video and replace it.

## Configuring Transcoder

The only configuration for Transcoder is in the `config.php` file, which is a multi-environment friendly way to store the default settings.  Don't edit this file, instead copy it to `craft/config` as `transcoder.php` and make your changes there.  Here's the default `config.php` file:

    <?php
    /**
     * Transcoder plugin for Craft CMS 3.x
     *
     * Transcode videos to various formats, and provide thumbnails of the video
     *
     * @link      https://nystudio107.com
     * @copyright Copyright (c) 2017 nystudio107
     */

    /**
     * Transcoder config.php
     *
     * Completely optional configuration settings for Transcoder if you want to
     * customize some of its more esoteric behavior, or just want specific control
     * over things.
     *
     * Don't edit this file, instead copy it to 'craft/config' as 'transcoder.php'
     * and make your changes there.
     *
     * Once copied to 'craft/config', this file will be multi-environment aware as
     * well, so you can have different settings groups for each environment, just as
     * you do for 'general.php'
     */

    return [

        // The path to the ffmpeg binary
        "ffmpegPath" => "/usr/bin/ffmpeg",

        // The path where the transcoded videos are stored
        "transcoderPath" => $_SERVER['DOCUMENT_ROOT'] . "/transcoder/",

        // The URL where the transcoded videos are stored
        "transcoderUrl" => "/transcoder/",

    ];

## Using Transcoder

### Generating a Transcoded Video

To generate a transcoded video, do the following:

    {% set transVideoUrl = craft.transcoder.getVideoUrl('/home/vagrant/sites/nystudio107/public/trimurti.mp4', {
        "frameRate": 20,
        "bitRate": "500k"
    }) %}

You can also pass in an `AssetFileModel`:

    {% set myAsset = entry.someAsset.first() %}
    {% set transVideoUrl = craft.transcoder.getVideoUrl(myAsset, {
        "frameRate": 20,
        "bitRate": "500k"
    }) %}

It will return to you a URL to the transcoded video if it already exists, or if it doesn't exist, it will return `""` and kick off the transcoding process (which can be quite lengthy for long videos).

In the array you pass in, the default values are used if the key/value pair does not exist:

    {
        "bitRate" => "800k",
        "frameRate" => 15,
    }

If you want to have the Transcoder not change a parameter, pass in an empty value in the key/value pair, e.g.:

    {% set transVideoUrl = craft.transcoder.getVideoUrl('/home/vagrant/sites/nystudio107/public/trimurti.mp4', {
        "frameRate": "",
        "bitRate": ""
    }) %}

The above example would cause it to not change the frameRate or bitRate of the source movie (not recommended for client-proofing purposes).

### Generating a Video Thumbnail

To generate a thumbnail from a video, do the following:

    {% set transVideoThumbUrl = craft.transcoder.getVideoThumbnailUrl('/home/vagrant/sites/nystudio107/public/trimurti.mp4', {
        "width": 300,
        "height": 200,
        "timeInSecs": 20,
    }) %}

It will return to you a URL to the thumbnail of the video, in the size you specify, from the timecode `timeInSecs` in the video.  It creates this thumbnail immediately if it doesn't already exist.

In the array you pass in, the default values are used if the key/value pair does not exist:

    {
        "width" => 200,
        "height" => 100,
        "timeInSecs" => 10,
    }

If you want to have the Transcoder not change a parameter, pass in an empty value in the key/value pair, e.g.:

    {% set transVideoThumbUrl = craft.transcoder.getVideoThumbnailUrl('/home/vagrant/sites/nystudio107/public/trimurti.mp4', {
        "width": "",
        "height": "",
        "timeInSecs": 20,
    }) %}

The above example would cause it to generate a thumbnail at whatever size the video is (not recommended for client-proofing purposes).

### Generating a Download URL

To generate a download URL for a file, do the following:

    {% set downloadUrl = craft.transcoder.getDownloadUrl('/some/url') %}

When the user clicks on the URL, it will download the file to their local computer.  If the file doesn't exist, `""` is returned.

The file must reside in the webroot (thus a URL or URI must be passed in as a parameter, not a path), for security reasons.

## Transcoder Roadmap

Some things to do, and ideas for potential features:

* The videos could potentially be saved in different formats (though `.mp4` really is "the" standard for video)
* The videos could potentially be resized, either to an aspect ratio or an absolute size or what have you
* Accessors could be written to get information about a video (height, width, duration, and so on)

## Transcoder Changelog

### 1.0.0 -- 2017.03.05

* Initial release

Brought to you by [nystudio107](https://nystudio107.com)
