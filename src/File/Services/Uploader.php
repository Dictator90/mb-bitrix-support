<?php

declare(strict_types=1);

namespace MB\Bitrix\File\Services;

use Bitrix\Main\Config\Option;
use MB\Bitrix\Contracts\Bitrix\ApplicationAdapter;
use MB\Bitrix\Contracts\File\Uploader as UploaderContract;
use MB\Bitrix\Filesystem\Filesystem;

final class Uploader implements UploaderContract
{
    public function __construct(private readonly ApplicationAdapter $applicationAdapter)
    {
    }

    public function save(array &$fileData): bool
    {
        $filesystem = Filesystem::instance();
        $path = (string) ($fileData['physical_path'] ?? '');
        if ($path === '') {
            return false;
        }

        try {
            if (isset($fileData['content'])) {
                $filesystem->put($path, $fileData['content']);
            } else {
                $tmpPath = $fileData['tmp_name'] ?? null;
                if (!is_string($tmpPath) || $tmpPath === '') {
                    return false;
                }

                $filesystem->makeDirectory(\dirname($path), 0755, true);

                if (!move_uploaded_file($tmpPath, $path)) {
                    $filesystem->copy($tmpPath, $path);
                }
            }

            if (defined('BX_FILE_PERMISSIONS')) {
                @chmod($path, BX_FILE_PERMISSIONS);
            }

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function delete(array $file): void
    {
        $uploadDir = Option::get('main', 'upload_dir', 'upload');
        $filePath = $this->applicationAdapter->getDocumentRoot() . '/' . $uploadDir . '/' . $file['SUBDIR'] . '/' . $file['FILE_NAME'];

        try {
            Filesystem::instance()->delete($filePath);
        } catch (\Throwable) {
            // Keep historical behavior: failed physical delete does not fail business operation.
        }
    }
}

