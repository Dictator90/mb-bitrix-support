<?php

namespace MB\Bitrix\Contracts\File;

/**
 * Интерфейс кэширования результатов обработки изображений
 * @package MB\Bitrix\File\Image
 */
interface ImageCache
{
    /**
     * Получает ID файла из кэша по ключу
     * @param string $key Ключ кэша
     * @return int|null ID файла или null если не найдено
     */
    public function get(string $key): ?int;

    /**
     * Сохраняет ID файла в кэш
     * @param string $key Ключ кэша
     * @param int $fileId ID файла
     * @return bool Успешность операции
     */
    public function set(string $key, int $fileId, int $originalFileId);

    /**
     * Очищает кэш для конкретного файла
     * @param int $fileId ID файла
     */
    public function clearForFile(int $fileId): void;
}