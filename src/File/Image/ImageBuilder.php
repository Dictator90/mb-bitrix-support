<?php

namespace MB\Bitrix\File\Image;

use Bitrix\Main\SystemException;
use MB\Bitrix\File\FileService;
use MB\Bitrix\File\Image\Operations\SpatieImageOperation;
use Spatie\ImageOptimizer\OptimizerChain;

/**
 * Fluent билдер для работы с Spatie Image
 *
 * Предоставляет удобный интерфейс для применения операций Spatie\Image\Image
 * с автоматическим кэшированием и интеграцией с 1С-Битрикс.
 *
 * @method self width(int $width, array $constraints = []) Устанавливает ширину изображения
 * @method self height(int $height, array $constraints = []) Устанавливает высоту изображения
 * @method self resize(int $width, int $height, array $constraints = []) Изменяет размер изображения
 * @method self fit(string $fit, ?int $width = null, ?int $height = null) Вписывает изображение с указанным методом
 * @method self crop(int $width, int $height, string $position = 'center') Обрезает изображение
 * @method self manualCrop(int $width, int $height, ?int $x = null, ?int $y = null) Ручная обрезка
 * @method self focalCrop(int $width, int $height, ?int $cropCenterX = null, ?int $cropCenterY = null) Фокусная обрезка
 * @method self focalCropAndResize(int $width, int $height, ?int $cropCenterX = null, ?int $cropCenterY = null) Фокусная обрезка с изменением размера
 * @method self brightness(int $brightness) Изменяет яркость (-100 до 100)
 * @method self contrast(float $level) Изменяет контрастность (-100 до 100)
 * @method self gamma(float $gamma) Корректирует гамму (0.1 до 9.99)
 * @method self blur(int $blur) Размытие (0 до 100)
 * @method self colorize(int $red, int $green, int $blue) Изменяет цветовой баланс
 * @method self greyscale() Преобразует в градации серого
 * @method self sepia() Применяет эффект сепии
 * @method self sharpen(float $amount) Повышает резкость (0 до 100)
 * @method self pixelate(int $pixelate = 50) Пикселизация (0 до 100)
 * @method self background(string $color) Изменяет фон изображения
 * @method self border(int $width, string $type = 'overlay', string $color = '000000') Добавляет рамку
 * @method self flip(string $direction) Отражает изображение (horizontal|vertical|both)
 * @method self orientation(?string $orientation = null) Изменяет ориентацию
 * @method self optimize(?OptimizerChain $optimizerChain = null) Оптимизирует изображение
 * @method self watermark(string $watermarkImage, string $position = 'bottom-right', int $paddingX = 0, int $paddingY = 0, int $width = 0, int $height = 0, string $fit = 'contain', int $alpha = 100) Добавляет водяной знак
 * @method self text(string $text, int $fontSize, string $color = '000000', int $x = 0, int $y = 0, int $angle = 0, string $fontPath = '', int $width = 0) Добавляет текст
 * @method self wrapText(string $text, int $fontSize, string $fontPath = '', int $angle = 0, int $width = 0) Обёртывание текста
 * @method self insert($otherImage, string $position = 'center', int $x = 0, int $y = 0, int $alpha = 100) Вставляет другое изображение
 * @method self overlay($bottomImage, $topImage, int $x, int $y) Накладывает изображения
 * @method self resizeCanvas(?int $width = null, ?int $height = null, ?string $position = null, bool $relative = false, string $backgroundColor = '#000000') Изменяет размер холста
 *
 * @package MB\Bitrix\File\Image
 */
class ImageBuilder
{
    private int $fileId;
    private array $operations = [];
    private ?string $format = null;
    private int $quality = 95;
    private ImageProcessor $processor;

    /**
     * @param int $fileId ID файла в Битрикс (b_file.ID)
     * @param ImageProcessor|null $processor Процессор изображений
     */
    public function __construct(int $fileId, ?ImageProcessor $processor = null)
    {
        $this->fileId = $fileId;
        $this->processor = $processor ?? new ImageProcessor();
    }

    /**
     * Магический метод для вызова операций Spatie Image
     *
     * Автоматически создает SpatieImageOperation для любого метода Spatie\Image\Image
     *
     * @param string $method Название метода Spatie
     * @param array $arguments Аргументы метода
     * @return self
     *
     * @throws \BadMethodCallException Если метод не поддерживается
     */
    public function __call(string $method, array $arguments): self
    {
        if (method_exists($this, $method)) {
            return call_user_func_array([$this, $method], $arguments);
        }

        $this->operations[] = SpatieImageOperation::create($method, $arguments);
        return $this;
    }

    /**
     * Устанавливает целевой формат изображения
     *
     * @param string $format Формат изображения (jpg, png, webp, gif, bmp)
     * @return self
     */
    public function format(string $format): self
    {
        $this->format = $format;
        return $this;
    }

    /**
     * Устанавливает качество сжатия
     *
     * @param int $quality Качество от 1 до 100
     * @return self
     */
    public function quality(int $quality): self
    {
        $this->quality = max(1, min(100, $quality));
        return $this;
    }

    /**
     * Добавляет кастомную операцию обработки
     *
     * @param \Closure $callback Колбэк, принимающий Spatie\Image\Image
     * @param string $name Название операции для кэширования
     * @return self
     */
    public function custom(\Closure $callback, string $name = 'custom'): self
    {
        $this->operations[] = SpatieImageOperation::createCustom($callback, $name);
        return $this;
    }

    /**
     * Выполняет все накопленные операции и сохраняет результат
     *
     * Автоматически использует кэш, если результат уже существует
     *
     * @param bool $returnArray
     * @return int|array|null ID нового файла в Битрикс
     * @throws SystemException
     */
    public function get(bool $returnArray = true): int|array|null
    {
        $fileId = $this->processor->process($this->fileId, $this->operations, $this->format, $this->quality);
        if ($returnArray) {
            return $this->getFileInfo($fileId);
        }
        return $fileId;
    }

    /**
     * Получает URL обработанного файла
     *
     * @param int|null $fileId ID файла (по умолчанию используется текущий fileId)
     * @return string URL файла или пустая строка если файл не найден
     */
    public function getUrl(?int $fileId = null): string
    {
        $fileId = $fileId ?: $this->fileId;
        $file = FileService::getFileData($fileId);
        return $file ? ($file['SRC'] ?? '') : '';
    }

    /**
     * Получает информацию о файле
     *
     * @param int|null $fileId ID файла
     * @return array|null Массив с информацией о файле или null
     */
    public function getFileInfo(?int $fileId = null): ?array
    {
        $fileId = $fileId ?: $this->fileId;
        return FileService::getFileData($fileId);
    }

    /**
     * Очищает кэш для текущего файла
     *
     * Удаляет все кэшированные результаты обработки этого файла
     */
    public function clearCache(): void
    {
        $cache = new DatabaseImageCache();
        $cache->clearForFile($this->fileId);
    }

    /**
     * Получает ID исходного файла
     *
     * @return int
     */
    public function getFileId(): int
    {
        return $this->fileId;
    }

    /**
     * Получает количество накопленных операций
     *
     * @return int
     */
    public function getOperationsCount(): int
    {
        return count($this->operations);
    }

    /**
     * Сбрасывает все накопленные операции
     *
     * @return self
     */
    public function reset(): self
    {
        $this->operations = [];
        $this->format = null;
        $this->quality = 95;
        return $this;
    }

    /**
     * Создает экземпляр билдера
     *
     * Фабричный метод для удобного создания
     *
     * @param int $fileId ID файла в Битрикс
     * @return self
     */
    public static function create(int $fileId): self
    {
        return new self($fileId);
    }

    /**
     * Удобный вход для типичных сценариев:
     *
     * Примеры:
     *  - ImageBuilder::preset($id, 'thumbnail_300')
     *  - ImageBuilder::preset($id, 'preview_1024')
     */
    public static function preset(int $fileId, string $preset): self
    {
        $builder = new self($fileId);

        return $builder->applyPreset($preset);
    }

    /**
     * Применяет заранее определённый пресет обработки.
     *
     * Здесь определяются только базовые, наиболее типичные пресеты.
     * При необходимости их можно расширить в пользовательском коде.
     */
    public function applyPreset(string $preset): self
    {
        return match ($preset) {
            'thumbnail_300' => $this
                ->fit('contain', 300, 300)
                ->quality(85),
            'preview_1024' => $this
                ->resize(1024, 1024, ['preserveAspectRatio' => true])
                ->quality(85),
            'avatar_square_256' => $this
                ->crop(256, 256, 'center')
                ->quality(90),
            default => $this,
        };
    }

    /**
     * Создает экземпляр билдера с кастомным процессором
     *
     * @param int $fileId ID файла в Битрикс
     * @param ImageProcessor $processor Кастомный процессор
     * @return self
     */
    public static function createWithProcessor(int $fileId, ImageProcessor $processor): self
    {
        return new self($fileId, $processor);
    }
}