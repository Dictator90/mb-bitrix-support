# File and Image Subsystem

This document describes `MB\Bitrix\File` and `MB\Bitrix\File\Image`.

## Resolution model

`FileService` is resolved through the container:

- `app('file.service')`
- `app(MB\Bitrix\Contracts\File\FileServiceContract::class)`

Static proxy resolution via `FileService::resolve()` is removed.

## FileService

Main class: `MB\Bitrix\File\FileService`  
Contract: `MB\Bitrix\Contracts\File\FileServiceContract`

Main operations:

- `save()` / `saveFile()` - save one file into Bitrix file storage.
- `saveFiles()` - save many files with per-item result payload.
- `getFileData()` / `getFilesData()` - read file metadata.
- `getFilePath()` / `getFilePathFromArray()` - resolve physical file paths.
- `updateDescription()` / `deleteFile()` - mutate file records.

## File decomposition

`FileService` delegates responsibilities to dedicated services:

- `Uploader`
- `DuplicateResolver`
- `MetadataReader`
- `FileRepository`

Bitrix-specific integration is moved behind adapters:

- `ApplicationAdapter`
- `QuotaAdapter`
- `LocalizationAdapter`

## Image subsystem

Primary classes:

- `MB\Bitrix\File\Image\Image` - low-level wrapper around `spatie/image`.
- `MB\Bitrix\File\Image\ImageProcessor` - processing pipeline with cache support.
- `MB\Bitrix\File\Image\ImageBuilder` - fluent API for common transformations.
- `MB\Bitrix\File\Image\BatchImageProcessor` - chunked batch processing.
- `MB\Bitrix\File\Image\DatabaseImageCache` / `NullImageCache` - cache backends.

Image classes now resolve file services via container binding and keep local fallback for non-bootstrapped runtime.

## Usage example

```php
use MB\Bitrix\Contracts\File\FileServiceContract;

/** @var FileServiceContract $files */
$files = app(FileServiceContract::class);

$fileId = $files->saveFile($_FILES['PHOTO'], 'my_module/photos');
$file = $fileId ? $files->getFileData($fileId) : null;
```

```php
use MB\Bitrix\File\Image\ImageBuilder;

$preview = ImageBuilder::create($fileId)
    ->resize(800, 600, ['preserveAspectRatio' => true])
    ->quality(85)
    ->get();
```
