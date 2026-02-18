<?php

namespace MB\Bitrix\File\Image;

use Bitrix\Main;
use MB\Bitrix\Contracts\File\ImageCache;

/**
 * Реализация кэширования результатов обработки изображений в БД
 * @package Bitrix\Main\File\Image
 */
class NullImageCache implements ImageCache
{
    public function __construct()
    {}

    public function get(string $key): ?int
    {
        return null;
    }

    public function set(string $key, int $fileId, int $originalFileId): Main\ORM\Data\AddResult
    {
        return new Main\ORM\Data\AddResult();
    }

    public function clearForFile(int $fileId): void
    {}

    private function delete(string $key): void
    {
    }
}