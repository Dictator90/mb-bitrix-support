<?php

namespace MB\Bitrix\Contracts\File;

use Spatie\Image\Image as SpatieImage;
use Bitrix\Main\SystemException;

/**
 * Интерфейс для операций над изображениями
 * @package MB\Bitrix\File\Image
 */
interface ImageOperation
{
    /**
     * Применяет операцию к изображению
     *
     * @param SpatieImage $image Изображение для обработки
     *
     * @throws SystemException
     */
    public function apply(SpatieImage $image): void;

    /**
     * Возвращает уникальное имя операции
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Возвращает параметры операции для кэширования
     *
     * @return array
     */
    public function getParams(): array;
}