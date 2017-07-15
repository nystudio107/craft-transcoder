# Transcoder Changelog

## 1.0.6 - 2017.07.15
### Changed
* Craft 3 beta 20 compatibility

## 1.0.5 - 2017.03.24
### Changed
* `hasSettings` -> `hasCpSettings` for Craft 3 beta 8 compatibility
* Added Craft 3 beta 8 compatible settings
* Modified config service calls for Craft 3 beta 8

## 1.0.4 - 2017.03.12
### Added
- Added `craft/cms` as a composer dependency
- Added code inspection typehinting for the plugin & services

### Changed
- Code refactor/cleanup

## 1.0.3 - 2017.03.11
### Added
- Use `php-shellcommand` to allow for proper execution on Windows & Unix servers

### Changed
- Minor code cleanup

## 1.0.2 - 2017.03.07
### Added
- Added a summary option to `getFileInfo()`

### Changed
- Refactored the `config.php` options to be more consistent

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
