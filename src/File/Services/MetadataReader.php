<?php

declare(strict_types=1);

namespace MB\Bitrix\File\Services;

use Bitrix\Main\Config\Option;
use Bitrix\Main\File\Image;
use Bitrix\Main\Web\MimeType;
use MB\Bitrix\Contracts\File\MetadataReader as MetadataReaderContract;

final class MetadataReader implements MetadataReaderContract
{
    public function contentType(string $path): string
    {
        if (function_exists('mime_content_type')) {
            return mime_content_type($path) ?: 'unknown';
        }

        $ext = substr($path, (int) strrpos($path, '.') + 1);

        return MimeType::getByFileExtension($ext) ?: 'unknown';
    }

    public function isImage(string $filename, string $mimeType = ''): bool
    {
        $ext = strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));
        $imageExtensions = explode(',', 'jpg,bmp,jpeg,jpe,gif,png,webp');

        if (in_array($ext, $imageExtensions, true)) {
            return (bool) MimeType::isImage($mimeType);
        }

        return false;
    }

    public function formatSize(int $size, int $precision = 2): string
    {
        $units = ['b', 'Kb', 'Mb', 'Gb', 'Tb'];
        $pos = 0;

        while ($size >= 1024 && $pos < 4) {
            $size /= 1024;
            $pos++;
        }

        $code = 'FILE_SIZE_' . $units[$pos];
        $title = \Bitrix\Main\Localization\Loc::getMessage($code) ?: $units[$pos];

        return round($size, $precision) . ' ' . $title;
    }

    public function hash(array $fileData): string
    {
        if (Option::get('main', 'control_file_duplicates', 'N') !== 'Y') {
            return '';
        }

        $maxSize = (int) Option::get('main', 'duplicates_max_size', '100') * 1024 * 1024;
        $size = (int) ($fileData['size'] ?? 0);

        if ($size > $maxSize && $maxSize !== 0) {
            return '';
        }

        if (array_key_exists('content', $fileData)) {
            return hash('md5', (string) $fileData['content']);
        }

        $tmpName = $fileData['tmp_name'] ?? null;
        if (!is_string($tmpName) || $tmpName === '') {
            return '';
        }

        return hash_file('md5', $tmpName) ?: '';
    }

    public function imageInfo(string $filePath): ?array
    {
        try {
            $image = new Image($filePath);
            $info = $image->getInfo();

            if ($info === null) {
                return null;
            }

            return [
                'width' => $info->getWidth(),
                'height' => $info->getHeight(),
            ];
        } catch (\Throwable) {
            return null;
        }
    }
}
