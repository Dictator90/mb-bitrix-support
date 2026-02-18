<?php

namespace MB\Bitrix\Contracts\File;

use Bitrix\Main\SystemException;

/**
 * Интерфейс основного процессора изображений
 * @package MB\Bitrix\File\Image
 */
interface ImageProcessor
{
    /**
     * Выполняет обработку изображения с кэшированием
     * @param int $fileId ID исходного файла
     * @param ImageOperation[] $operations Массив операций
     * @param string|null $format Целевой формат
     * @param int $quality Качество (1-100)
     * @return int ID обработанного файла
     * @throws SystemException
     */
    public function process(int $fileId, array $operations, ?string $format = null, int $quality = 95): int;

    /**
     * Проверяет наличие закешированного результата
     * @param int $fileId ID исходного файла
     * @param ImageOperation[] $operations Массив операций
     * @param string|null $format Целевой формат
     * @param int|null $quality Качество (1-100), если учитывается в ключе кэша
     * @return int|null ID файла или null если не найдено
     */
    public function getCached(int $fileId, array $operations, ?string $format = null, ?int $quality = null): ?int;
}