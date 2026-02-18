<?php
// /local/modules/mb.core/lib/support/file/image/operations/SpatieImageOperation.php

namespace MB\Bitrix\File\Image\Operations;

use MB\Bitrix\Contracts\File\ImageOperation;
use Spatie\Image\Image as SpatieImage;

/**
 * Операция, работающая напрямую с Spatie Image
 * Полностью bypass Bitrix\Main\File\Image
 */
class SpatieImageOperation implements ImageOperation
{
    private string $name;
    private array $params = [];
    private \Closure $callback;

    public function __construct(string $name, \Closure $callback, array $params = [])
    {
        $this->name = $name;
        $this->callback = $callback;
        $this->params = $params;
    }

    /**
     * Применяет операцию напрямую к Spatie Image
     * @param mixed $image Путь к файлу или объект SpatieImage
     */
    public function apply($image): void
    {
        // Если передан путь к файлу, загружаем его
        if (is_string($image)) {
            $spatieImage = SpatieImage::load($image);
        } else {
            $spatieImage = $image;
        }

        ($this->callback)($spatieImage);
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * {@inheritdoc}
     */
    public function getParams(): array
    {
        return $this->params;
    }

    /**
     * Создает операцию для метода Spatie
     */
    public static function create(string $method, array $arguments = []): self
    {
        return new self(
            'spatie_' . $method,
            function(SpatieImage $spatieImage) use ($method, $arguments) {
                call_user_func_array([$spatieImage, $method], $arguments);
            },
            ['method' => $method, 'arguments' => $arguments]
        );
    }

    /**
     * Создает операцию с кастомным колбэком
     */
    public static function createCustom(\Closure $callback, string $name = 'custom'): self
    {
        return new self('custom_' . $name, $callback, ['custom' => true]);
    }

    /**
     * Фабричные методы для часто используемых операций
     */
    public static function resize(int $width, int $height, array $constraints = []): self
    {
        return self::create('resize', [$width, $height, $constraints]);
    }

    public static function width(int $width, array $constraints = []): self
    {
        return self::create('width', [$width, $constraints]);
    }

    public static function height(int $height, array $constraints = []): self
    {
        return self::create('height', [$height, $constraints]);
    }

    public static function fit(string $fit, ?int $width = null, ?int $height = null): self
    {
        return self::create('fit', [$fit, $width, $height]);
    }

    public static function crop(int $width, int $height, string $position = 'center'): self
    {
        return self::create('crop', [$width, $height, $position]);
    }

    public static function optimize(?array $options = null): self
    {
        return self::create('optimize', [$options]);
    }

    public static function quality(int $quality): self
    {
        return self::create('quality', [$quality]);
    }

    public static function format(string $format): self
    {
        return self::create('format', [$format]);
    }

    public static function watermark(string $watermarkPath, string $position = 'bottom-right'): self
    {
        return self::create('watermark', [$watermarkPath, $position]);
    }

    public static function brightness(int $level): self
    {
        return self::create('brightness', [$level]);
    }

    public static function greyscale(): self
    {
        return self::create('greyscale', []);
    }

    /**
     * Магический метод для создания любой операции Spatie
     */
    public static function __callStatic(string $method, array $arguments): self
    {
        return self::create($method, $arguments);
    }
}