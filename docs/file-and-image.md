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

## Writes go through `CFile`

`b_file` rows are written with `CFile::DoInsert()` / `CFile::UpdateDesc()` / `CFile::Delete()`.
The D7 ORM has no write path here: `\Bitrix\Main\FileTable::add()` / `update()` / `delete()`
throw `NotImplementedException('Use CFile class.')`. Reads stay on `FileTable::query()`.

`CFile::Delete()` also removes the physical copy, generated resizes, the hash row, duplicate
bookkeeping and cached buckets, fires `OnFileDelete` / `OnPhysicalFileDelete` and updates the
disk quota. A file that other rows duplicate is marked deleted and dropped together with the
last reference, so `deleteFile()` returns `true` while the row is still visible; `false` means
there is no file with that ID.

Physical writes still go through `Uploader` (rooted at `ApplicationAdapter::getDocumentRoot()`),
while `CFile::Delete()` resolves paths from `$_SERVER['DOCUMENT_ROOT']` - the two must point at
the same root.

## File decomposition

`FileService` delegates responsibilities to dedicated services:

- `Uploader`
- `DuplicateResolver` - with `control_file_duplicates=Y` registers a new `b_file` row over the
  existing physical copy plus a `b_file_duplicate` reference, same as `CFile::SaveFile()`
- `MetadataReader`
- `FileRepository` - the injectable write seam; `FileService` currently writes through `CFile`
  directly and does not call it

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
