<?php

namespace MB\Bitrix\File\Image;

use Bitrix\Main;
use Bitrix\Main\Diag\Debug;
use MB\Bitrix\File\Image\Operations\SpatieImageOperation;
use Spatie\ImageOptimizer\OptimizerChain;

/**
 * Пакетный процессор изображений с Fluent интерфейсом
 *
 * Поддерживает добавление файлов в пакет через addToBatch() и обработку с чанками
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
class BatchImageProcessor
{
    private array $fileIds = [];
    private array $operations = [];
    private ?string $format = null;
    private int $quality = 95;
    private int $chunkSize = 10;
    private ?\Closure $progressCallback = null;
    private ?\Closure $errorCallback = null;
    private ?ImageProcessor $processor = null;
    private bool $useCache = true;

    /**
     * Конструктор
     *
     * @param array $fileIds Массив ID файлов (опционально)
     * @param ImageProcessor|null $processor Процессор изображений
     * @param int $chunkSize Размер чанка для обработки
     */
    public function __construct(array $fileIds = [], ?ImageProcessor $processor = null)
    {
        $this->fileIds = array_map('intval', $fileIds);
        $this->processor = $processor ?? new ImageProcessor();
    }

    /**
     * Добавляет файлы в пакет обработки
     *
     * @param int|array $fileIds Один ID файла или массив ID файлов
     * @return self
     */
    public function addToBatch(int|string|array $fileIds): self
    {
        if (is_array($fileIds)) {
            $this->fileIds = array_merge($this->fileIds, array_map('intval', $fileIds));
        } else {
            $this->fileIds[] = (int)$fileIds;
        }

        $this->fileIds = array_unique($this->fileIds);

        return $this;
    }

    /**
     * Добавляет операцию обработки
     *
     * @param string|SpatieImageOperation $operation Операция или имя метода Spatie
     * @param array $arguments Аргументы метода (если передано имя метода)
     * @return self
     */
    public function addOperation(SpatieImageOperation|string $operation, array $arguments = []): self
    {
        if (is_string($operation) && class_exists($operation)) {
            $this->operations[] = new $operation(...$arguments);
        } elseif (is_string($operation)) {
            $this->operations[] = SpatieImageOperation::create($operation, $arguments);
        } elseif ($operation instanceof SpatieImageOperation) {
            $this->operations[] = $operation;
        } else {
            throw new \InvalidArgumentException(
                'Operation must be SpatieImageOperation instance or method name'
            );
        }

        return $this;
    }

    /**
     * Магический метод для быстрого добавления операций Spatie
     *
     * @param string $method Имя метода Spatie
     * @param array $arguments Аргументы метода
     * @return self
     */
    public function __call(string $method, array $arguments): self
    {
        return $this->addOperation($method, $arguments);
    }

    /**
     * Устанавливает целевой формат
     *
     * @param string $format Формат изображения
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
     * Устанавливает размер чанка
     *
     * @param int $chunkSize Размер чанка
     * @return self
     */
    public function chunkSize(int $chunkSize): self
    {
        $this->chunkSize = max(1, $chunkSize);
        return $this;
    }

    /**
     * Включает/выключает кэширование
     *
     * @param bool $useCache Использовать кэш
     * @return self
     */
    public function useCache(bool $useCache = true): self
    {
        $this->useCache = $useCache;
        return $this;
    }

    /**
     * Устанавливает колбэк для отслеживания прогресса
     *
     * @param \Closure $callback Колбэк (текущий чанк, всего чанков, результаты)
     * @return self
     */
    public function onProgress(\Closure $callback): self
    {
        $this->progressCallback = $callback;
        return $this;
    }

    /**
     * Устанавливает колбэк для обработки ошибок
     *
     * @param \Closure $callback Колбэк (fileId, исключение)
     * @return self
     */
    public function onError(\Closure $callback): self
    {
        $this->errorCallback = $callback;
        return $this;
    }

    /**
     * Выполняет обработку файлов с динамическими операциями
     *
     * @param array $fileIds Массив ID файлов (опционально, если не установлены через addToBatch)
     * @param callable $callback Колбэк для генерации операций для каждого файла
     * @return array Результаты обработки
     */
    public function processDynamic(array $fileIds = [], callable $callback): array
    {
        // Добавляем переданные файлы, если есть
        if (!empty($fileIds)) {
            $this->addToBatch($fileIds);
        }

        if (empty($this->fileIds)) {
            throw new \InvalidArgumentException('No files to process. Use addToBatch() first.');
        }

        $results = [];
        $chunks = array_chunk($this->fileIds, $this->chunkSize);
        $totalChunks = count($chunks);

        foreach ($chunks as $chunkIndex => $chunk) {
            $chunkNumber = $chunkIndex + 1;

            foreach ($chunk as $fileId) {
                try {
                    $startTime = microtime(true);

                    // Генерируем операции для конкретного файла
                    $fileOperations = $callback($fileId);
                    if (!is_array($fileOperations)) {
                        $fileOperations = [];
                    }

                    // Обрабатываем файл
                    $resultId = $this->processFile($fileId, $fileOperations);

                    $results[$fileId] = [
                        'success' => true,
                        'file_id' => $resultId,
                        'original_id' => $fileId,
                        'processing_time' => round(microtime(true) - $startTime, 3),
                        'chunk' => $chunkNumber,
                        'operations_count' => count($fileOperations)
                    ];

                } catch (\Exception $e) {
                    $results[$fileId] = [
                        'success' => false,
                        'error' => $e->getMessage(),
                        'original_id' => $fileId,
                        'chunk' => $chunkNumber
                    ];

                    $this->handleError($fileId, $e);
                }
            }

            $this->handleProgress($chunkNumber, $totalChunks, $results);
            $this->sleepBetweenChunks();
        }

        return $results;
    }

    /**
     * Выполняет обработку файлов с установленными операциями
     *
     * @return array Результаты обработки
     */
    public function get(): array
    {
        if (empty($this->fileIds)) {
            throw new \InvalidArgumentException('No files to process. Use addToBatch() first.');
        }

        $results = [];
        $chunks = array_chunk($this->fileIds, $this->chunkSize);
        $totalChunks = count($chunks);

        foreach ($chunks as $chunkIndex => $chunk) {
            $chunkNumber = $chunkIndex + 1;

            foreach ($chunk as $fileId) {
                try {
                    $startTime = microtime(true);

                    // Обрабатываем файл с установленными операциями
                    $resultId = $this->processFile($fileId, $this->operations);

                    $results[$fileId] = [
                        'success' => true,
                        'file_id' => $resultId,
                        'original_id' => $fileId,
                        'processing_time' => round(microtime(true) - $startTime, 3),
                        'chunk' => $chunkNumber,
                        'operations_count' => count($this->operations)
                    ];

                } catch (\Exception $e) {
                    $results[$fileId] = [
                        'success' => false,
                        'error' => $e->getMessage(),
                        'original_id' => $fileId,
                        'chunk' => $chunkNumber
                    ];

                    $this->handleError($fileId, $e);
                }
            }

            $this->handleProgress($chunkNumber, $totalChunks, $results);
            $this->sleepBetweenChunks();
        }

        return $results;
    }

    /**
     * Обрабатывает один файл с учетом кэширования
     */
    private function processFile(int $fileId, array $operations): int
    {
        if (!$this->useCache) {
            $this->processor->setCacheEngine(new NullImageCache());
        }

        return $this->processor->process($fileId, $operations, $this->format, $this->quality);
    }

    /**
     * Сбрасывает все настройки
     *
     * @return self
     */
    public function reset(): self
    {
        $this->fileIds = [];
        $this->operations = [];
        $this->format = null;
        $this->quality = 95;
        $this->useCache = true;
        $this->progressCallback = null;
        $this->errorCallback = null;

        return $this;
    }

    /**
     * Получает количество файлов в пакете
     *
     * @return int
     */
    public function getBatchSize(): int
    {
        return count($this->fileIds);
    }

    /**
     * Получает массив ID файлов в пакете
     *
     * @return array
     */
    public function getFileIds(): array
    {
        return $this->fileIds;
    }

    /**
     * Получает количество установленных операций
     *
     * @return int
     */
    public function getOperationsCount(): int
    {
        return count($this->operations);
    }

    /**
     * Получает установленные операции
     *
     * @return array
     */
    public function getOperations(): array
    {
        return $this->operations;
    }

    /**
     * Удаляет файл из пакета
     *
     * @param int $fileId ID файла
     * @return self
     */
    public function removeFromBatch(int $fileId): self
    {
        $this->fileIds = array_filter($this->fileIds, fn($id) => $id !== $fileId);
        return $this;
    }

    /**
     * Очищает пакет от файлов (но сохраняет операции и настройки)
     *
     * @return self
     */
    public function clearBatch(): self
    {
        $this->fileIds = [];
        return $this;
    }

    /**
     * Создает экземпляр процессора
     *
     * @param array $fileIds Массив ID файлов (опционально)
     * @param int $chunkSize Размер чанка
     * @return self
     */
    public static function create(array $fileIds = [], int $chunkSize = 10): self
    {
        return new self($fileIds, null, $chunkSize);
    }

    /**
     * Создает экземпляр с кастомным процессором
     *
     * @param ImageProcessor $processor Процессор изображений
     * @param array $fileIds Массив ID файлов (опционально)
     * @param int $chunkSize Размер чанка
     * @return self
     */
    public static function createWithProcessor(ImageProcessor $processor, array $fileIds = [], int $chunkSize = 10): self
    {
        return new self($fileIds, $processor, $chunkSize);
    }

    private function handleProgress(int $currentChunk, int $totalChunks, array $results): void
    {
        if ($this->progressCallback) {
            ($this->progressCallback)($currentChunk, $totalChunks, $results);
        }
    }

    private function handleError(int $fileId, \Exception $e): void
    {
        if ($this->errorCallback) {
            ($this->errorCallback)($fileId, $e);
        }
    }

    private function sleepBetweenChunks(): void
    {
        if ($this->chunkSize > 1) {
            usleep(100000);
        }
    }
}