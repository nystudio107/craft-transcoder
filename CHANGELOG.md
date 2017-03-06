# Transcoder Changelog

## 1.0.1 - 2017.03.06
### Added
- Added `height` and `width` options for resizing the videos
- Added an `aspectRatio` option to control how aspect ratio scaling is done
- Added a `letterboxColor` option
- Added a `sharpen` option
- Added the `getFileInfo` variable to extract information from a video/audio file
- The `ffmpeg` progress for video transcoding is now written out to a `.progress` file
- Added a `progress` controller to return video transcoding progress
- Moved all of the default settings out to the `config.php` file
- Added support for multiple video encoding formats
- Added the ability to transcode audio files
- Transcoder caches can be cleared via the ClearCaches utility

### Fixed
- Fixed some issues with the lockfile naming

## 1.0.0 - 2017.03.05
### Added
- Initial release
