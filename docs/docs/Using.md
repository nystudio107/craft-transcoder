# Using Transcoder

## Generating a Transcoded Video

To generate a transcoded video, do the following:

    {% set transVideoUrl = craft.transcoder.getVideoUrl('/home/vagrant/sites/nystudio107/public/oceans.mp4', {
        "videoFrameRate": 20,
        "videoBitRate": "500k",
        "width": 720,
        "height": 480
    }) %}

You can also pass in an URL:

    {% set transVideoUrl = craft.transcoder.getVideoUrl('http://vjs.zencdn.net/v/oceans.mp4', {
        "videoFrameRate": 20,
        "videoBitRate": "500k",
        "width": 720,
        "height": 480
    }) %}

You can also pass in an `Asset`:

    {% set myAsset = entry.someAsset.one() %}
    {% set transVideoUrl = craft.transcoder.getVideoUrl(myAsset, {
        "videoFrameRate": 20,
        "videoBitRate": "500k",
        "width": 720,
        "height": 480
    }) %}

It will return to you a URL to the transcoded video if it already exists, or if it doesn't exist, it will return `""` and kick off the transcoding process (which can be quite lengthy for long videos).

In the array you pass in, the default values are used if the key/value pair does not exist:

    {
        "videoEncoder" => "h264",
        "videoBitRate" => "800k",
        "videoFrameRate" => 15,
        "aspectRatio" => "letterbox",
        "sharpen" => true,
    }

These default values come from the `config.php` file.

If you want to have the Transcoder not change a parameter, pass in an empty value in the key/value pair, e.g.:

    {% set transVideoUrl = craft.transcoder.getVideoUrl('/home/vagrant/sites/nystudio107/public/oceans.mp4', {
        "frameRate": "",
        "bitRate": ""
    }) %}

The above example would cause it to not change the frameRate or bitRate of the source video (not recommended for client-proofing purposes).

The `aspectRatio` parameter lets you control how the video aspect ratio is maintained when it is scaled:

`none` results in the aspect ratio of the original video not being maintained, and the video scaled to the dimensions passed in:

![Screenshot](./resources/screenshots/oceans_20s_300w_200h_none.jpg)

`crop` scales the video up to maintain the original aspect ratio, and then crops it so that it's full-frame:

![Screenshot](./resources/screenshots/oceans_20s_300w_200h_crop.jpg)

`letterbox` scales the video to fit the new frame, putting a letterboxed or pillarboxed border to pad it:

![Screenshot](./resources/screenshots/oceans_20s_300w_200h_letterbox.jpg)

You can control the color of the letterboxed area (it's `black` by default) via the `letterboxColor` option. It can be either a semantic color name, or a hexcode color, e.g.: `0xC0C0C0`

The `sharpen` option determines whether an unsharp mask filter should be applied to the scaled video.

The file format setting `videoEncoder` is preset to what you'll need to generate `h264` videos, but it can also generate `webm` videos, or any other format that `ffmpeg` supports. See the `config.php` file for details

![Screenshot](./resources/screenshots/admin-cp-video-thumbnails.png)

Transcoder will also automatically add video thumbnails in the Control Panel Asset index.

## Generating a Transcoded Audio File

To generate a transcoded audio File, do the following:

    {% set transAudioUrl = craft.transcoder.getAudioUrl('/home/vagrant/sites/nystudio107/public/podcast.mp3', {
        "audioBitRate": "64k",
        "audioSampleRate": 22050,
        "audioChannels": 1
    }) %}

You can also pass in a URL:

    {% set transAudioUrl = craft.transcoder.getAudioUrl('http://www.noiseaddicts.com/samples_1w72b820/2514.mp3', {
        "audioBitRate": "64k",
        "audioSampleRate": 22050,
        "audioChannels": 1
    }) %}

You can also pass in an `Asset`:

    {% set myAsset = entry.someAsset.one() %}
    {% set transAudioUrl = craft.transcoder.getAudioUrl(myAsset, {
        "audioBitRate": "64k",
        "audioSampleRate": 22050,
        "audioChannels": 1
    }) %}

It will return to you a URL to the transcoded audio file if it already exists, or if it doesn't exist, it will return `""` and kick off the transcoding process (which can be somewhat lengthy for long audio files).

In the array you pass in, the default values are used if the key/value pair does not exist:

    {
        "audioEncoder" => "mp3",
        "audioBitRate" => "128k",
        "audioSampleRate" => "44100",
        "audioChannels" => "2",
        'synchronous' => false,
        'stripMetadata' => false,
    }

These default values come from the `config.php` file.

If you want to have the Transcoder not change a parameter, pass in an empty value in the key/value pair, e.g.:

    {% set transVideoUrl = craft.transcoder.getVideoUrl('/home/vagrant/sites/nystudio107/public/trimurti.mp4', {
        "audioBitRate": "",
        "audioSampleRate": "",
        "audioChannels": ""
    }) %}

The above example would cause it to not change the audio of the source audio file at all (not recommended for client-proofing purposes).

The file format setting `audioEncoder` is preset to what you'll need to generate `mp3` audio files, but it can also generate `aac`, `ogg`, or any other format that `ffmpeg` supports. See the `config.php` file for details

## Getting Transcoding Progress

Transcoding of video/audio files can take quite a bit of time, so Transcoder provides you with a way to get the status of any currently running transcoding operation via `craft.transcoder.getVideoProgressUrl()` or `craft.transcoder.getAudioProgressUrl()`. For example:

    {% set myAsset = entry.someAsset.one() %}
    {% set videoOptions = {
        "videoFrameRate": 60,
        "videoBitRate": "1000k",
        "width": 1000,
        "height": 800,
        "aspectRatio": "none",
    } %}
    {% set transVideoUrl = craft.transcoder.getVideoUrl(myAsset, videoOptions) %}
    {% set progressUrl = craft.transcoder.getVideoProgressUrl(myAsset, videoOptions) %}

The variable `progressUrl` in the example above is set to a URL will return a JSON array of data indicating the current progress of the transcoding:
 
     {
       "filename": "oceans_1000kbps_60fps_1000w_800h_letterbox.mp4",
       "duration": 46.61,
       "time": 37.69,
       "progress": 81
     }

* `filename` - the name of the file
* `duration` - the duration of the video/audio stream
* `time` - the time of the current encoding
* `progress` - a percentage indicating how much of the encoding is done

You can use this information to provide a progress bar via JavaScript or from a plugin.

## Generating a Video Thumbnail

To generate a thumbnail from a video, do the following:

    {% set transVideoThumbUrl = craft.transcoder.getVideoThumbnailUrl('/home/vagrant/sites/nystudio107/public/oceans.mp4', {
        "width": 300,
        "height": 200,
        "timeInSecs": 20,
    }) %}

You can also pass in a URL:

    {% set transVideoUrl = craft.transcoder.getVideoUrl('http://vjs.zencdn.net/v/oceans.mp4', {
        "width": 300,
        "height": 200,
        "timeInSecs": 20,
    }) %}

You can also pass in an `Asset`:

    {% set myAsset = entry.someAsset.one() %}
    {% set transVideoUrl = craft.transcoder.getVideoUrl(myAsset, {
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
        "aspectRatio" => "letterbox",
        "sharpen" => true,
    }

If you want to have the Transcoder not change a parameter, pass in an empty value in the key/value pair, e.g.:

    {% set transVideoThumbUrl = craft.transcoder.getVideoThumbnailUrl('/home/vagrant/sites/nystudio107/public/oceans.mp4', {
        "width": "",
        "height": "",
        "timeInSecs": 20,
    }) %}

The above example would cause it to generate a thumbnail at whatever size the video is (not recommended for client-proofing purposes).

The `aspectRatio` parameter lets you control how the video aspect ratio is maintained when it is scaled:

`none` results in the aspect ratio of the original video not being maintained, and the thumbnail image is scaled to the dimensions passed in:

![Screenshot](./resources/screenshots/oceans_20s_300w_200h_none.jpg)

`crop` scales the video up to maintain the original aspect ratio, and then crops it so that the thumbnail image is full-frame:

![Screenshot](./resources/screenshots/oceans_20s_300w_200h_crop.jpg)

`letterbox` scales the video to fit the new frame, putting a letterboxed or pillarboxed border to pad the thumbnail image:

![Screenshot](./resources/screenshots/oceans_20s_300w_200h_letterbox.jpg)

You can control the color of the letterboxed area (it's `black` by default) via the `letterboxColor` option. It can be either a semantic color name, or a hexcode color, e.g.: `0xC0C0C0`

The `sharpen` option determines whether an unsharp mask filter should be applied to the scaled thumbnail image.

## Getting Information About a Video/Audio File

To get information about an existing video/audio file, you can use `craft.transcoder.getFileInfo()`:

    {% set fileInfo = craft.transcoder.getFileInfo('/home/vagrant/sites/nystudio107/public/oceans.mp4', true) %}

You can also pass in a URL:

    {% set fileInfo = craft.transcoder.getFileInfo('http://vjs.zencdn.net/v/oceans.mp4', true) %}

You can also pass in an `Asset`:

    {% set myAsset = entry.someAsset.one() %}
    {% set fileInfo = craft.transcoder.getFileInfo(myAsset, true) %}

By passing in `true` as the second argument, we get just a summary of the video/audio file information in an array:

    [
        'videoEncoder' => 'h264'
        'videoBitRate' => '3859635'
        'videoFrameRate' => 23.976023976024
        'height' => 400
        'width' => 960
        'audioEncoder' => 'aac'
        'audioBitRate' => '92926'
        'audioSampleRate' => '48000'
        'audioChannels' => 2
        'filename' => '/htdocs/craft3/public/assets/oceans.mp4'
        'duration' => '46.613333'
        'size' => '23014356'
    ]

If you instead pass in `false` as the second parameter (or omit it), then `craft.transcoder.getFileInfo()` returns the full video/audio file info an array with two top-level keys:

* `format` - information about the container file format
* `streams` - information about each stream in the container; many videos have multiple streams, for instance, one for the video streams, and another for the audio stream. There can even be multiple video or audio streams in a container.

Here's example output from `craft.transcoder.getFileInfo()`:

    [
        'streams' => [
            0 => [
                'index' => 0
                'codec_name' => 'h264'
                'codec_long_name' => 'H.264 / AVC / MPEG-4 AVC / MPEG-4 part 10'
                'profile' => 'Constrained Baseline'
                'codec_type' => 'video'
                'codec_time_base' => '1001/48000'
                'codec_tag_string' => 'avc1'
                'codec_tag' => '0x31637661'
                'width' => 960
                'height' => 400
                'coded_width' => 960
                'coded_height' => 400
                'has_b_frames' => 0
                'sample_aspect_ratio' => '1:1'
                'display_aspect_ratio' => '12:5'
                'pix_fmt' => 'yuv420p'
                'level' => 30
                'chroma_location' => 'left'
                'refs' => 3
                'is_avc' => '1'
                'nal_length_size' => '4'
                'r_frame_rate' => '24000/1001'
                'avg_frame_rate' => '24000/1001'
                'time_base' => '1/24000'
                'start_pts' => 0
                'start_time' => '0.000000'
                'duration_ts' => 1117116
                'duration' => '46.546500'
                'bit_rate' => '3859635'
                'bits_per_raw_sample' => '8'
                'nb_frames' => '1116'
                'disposition' => [
                    'default' => 1
                    'dub' => 0
                    'original' => 0
                    'comment' => 0
                    'lyrics' => 0
                    'karaoke' => 0
                    'forced' => 0
                    'hearing_impaired' => 0
                    'visual_impaired' => 0
                    'clean_effects' => 0
                    'attached_pic' => 0
                ]
                'tags' => [
                    'creation_time' => '2013-05-03 22:50:47'
                    'language' => 'und'
                    'handler_name' => 'GPAC ISO Video Handler'
                ]
            ]
            1 => [
                'index' => 1
                'codec_name' => 'aac'
                'codec_long_name' => 'AAC (Advanced Audio Coding)'
                'profile' => 'LC'
                'codec_type' => 'audio'
                'codec_time_base' => '1/48000'
                'codec_tag_string' => 'mp4a'
                'codec_tag' => '0x6134706d'
                'sample_fmt' => 'fltp'
                'sample_rate' => '48000'
                'channels' => 2
                'channel_layout' => 'stereo'
                'bits_per_sample' => 0
                'r_frame_rate' => '0/0'
                'avg_frame_rate' => '0/0'
                'time_base' => '1/48000'
                'start_pts' => 0
                'start_time' => '0.000000'
                'duration_ts' => 2237440
                'duration' => '46.613333'
                'bit_rate' => '92926'
                'max_bit_rate' => '104944'
                'nb_frames' => '2185'
                'disposition' => [
                    'default' => 1
                    'dub' => 0
                    'original' => 0
                    'comment' => 0
                    'lyrics' => 0
                    'karaoke' => 0
                    'forced' => 0
                    'hearing_impaired' => 0
                    'visual_impaired' => 0
                    'clean_effects' => 0
                    'attached_pic' => 0
                ]
                'tags' => [
                    'creation_time' => '2013-05-03 22:51:07'
                    'language' => 'und'
                    'handler_name' => 'GPAC ISO Audio Handler'
                ]
            ]
        ]
        'format' => [
            'filename' => '/htdocs/craft3/public/assets/oceans.mp4'
            'nb_streams' => 2
            'nb_programs' => 0
            'format_name' => 'mov,mp4,m4a,3gp,3g2,mj2'
            'format_long_name' => 'QuickTime / MOV'
            'start_time' => '0.000000'
            'duration' => '46.613333'
            'size' => '23014356'
            'bit_rate' => '3949832'
            'probe_score' => 100
            'tags' => [
                'major_brand' => 'isom'
                'minor_version' => '1'
                'compatible_brands' => 'isomavc1'
                'creation_time' => '2013-05-03 22:51:07'
            ]
        ]
    ]

## Generating a Download URL

To generate a download URL for a file, do the following:

    {% set downloadUrl = craft.transcoder.getDownloadUrl('/some/url') %}

When the user clicks on the URL, it will download the file to their local computer.  If the file doesn't exist, `""` is returned.

The file must reside in the webroot (thus a URL or URI must be passed in as a parameter, not a path), for security reasons.

Brought to you by [nystudio107](https://nystudio107.com)
