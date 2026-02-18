<?php
// /local/modules/mb.core/lib/support/file/image/SpatieImageHelper.php

namespace MB\Bitrix\File\Image;

use MB\Bitrix\File\Image\Operations\SpatieImageOperation;
use Spatie\ImageOptimizer\OptimizerChainFactory;

/**
 * Хелпер для быстрых операций с Spatie
 */
class ImageHelper
{
    /**
     * Создает билдер для файла
     */
    public static function builder(int $fileId): ImageBuilder
    {
        return new ImageBuilder($fileId);
    }

    /**
     * Создает миниатюру
     */
    public static function thumbnail(int $fileId, int $width, int $height): int
    {
        $builder = self::builder($fileId);
        return $builder
            ->width($width)
            ->height($height)
            ->quality(85)
            ->get();
    }

    /**
     * Конвертирует в формат
     */
    public static function convert(int $fileId, string $format, int $quality = 90): int
    {
        $builder = self::builder($fileId);
        return $builder
            ->format($format)
            ->quality($quality)
            ->get();
    }

    /**
     * Добавляет водяной знак
     */
    public static function addWatermark(int $fileId, string $watermarkPath, string $position = 'bottom-right'): int
    {
        $builder = self::builder($fileId);
        return $builder
            ->watermark($watermarkPath, $position)
            ->quality(90)
            ->get();
    }

    /**
     * Оптимизирует изображение
     */
    public static function optimize(int $fileId, int $quality = 85): int
    {
        $builder = self::builder($fileId);
        return $builder
            ->optimize(OptimizerChainFactory::create())
            ->quality($quality)
            ->get();
    }

    /**
     * Создает пакетный процессор
     */
    public static function batch(int $chunkSize = 10): BatchImageProcessor
    {
        return new BatchImageProcessor($chunkSize);
    }

    /**
     * Пакетная обработка миниатюр
     */
    public static function batchThumbnails(array $fileIds, int $width, int $height, int $chunkSize = 10): array
    {
        $operations = [
            SpatieImageOperation::width($width),
            SpatieImageOperation::height($height),
            SpatieImageOperation::optimize()
        ];

        $processor = new BatchImageProcessor($chunkSize);
        return $processor->process($fileIds, $operations, null, 85);
    }
}