<?php
namespace MB\Bitrix\File\Image;

use Bitrix\Main;
use MB\Bitrix\Contracts\File\ImageCache as ImageCacheContract;
use MB\Bitrix\Contracts\File\ImageProcessor as ImageProcessorContract;
use MB\Bitrix\File\FileService;
use MB\Bitrix\File\Image\Operations\SpatieImageOperation;
use MB\Bitrix\Filesystem\Filesystem;
use Spatie\Image\Image as SpatieImage;

/**
 * Процессор, работающий исключительно через Spatie Image
 */
class ImageProcessor implements ImageProcessorContract
{
    private array $fileArray = [];

    public function __construct(private ImageCacheContract|null $cache = null)
    {
        // Позволяет подменять реализацию кэша извне,
        // по умолчанию используется кэш в БД.
        $this->cache = $cache ?? new DatabaseImageCache();
    }

    /**
     * {@inheritdoc}
     */
    public function process(int $fileId, array $operations, ?string $format = null, int $quality = 95): int
    {
        $cachedResult = $this->getCached($fileId, $operations, $format, $quality);
        if ($cachedResult !== null) {
            return $cachedResult;
        }

        $resultId = $this->executeProcessing($fileId, $operations, $format, $quality);

        $cacheKey = $this->generateCacheKey($fileId, $operations, $format, $quality);
        $this->cache->set($cacheKey, $resultId, $fileId);

        return $resultId;
    }

    /**
     * {@inheritdoc}
     */
    public function getCached(int $fileId, array $operations, ?string $format = null, ?int $quality = null): ?int
    {
        $cacheKey = $this->generateCacheKey($fileId, $operations, $format, $quality);
        return $this->cache->get($cacheKey);
    }

    public function setCacheEngine(ImageCacheContract $cache): self
    {
        $this->cache = $cache;
        return $this;
    }
    /**
     * Выполняет обработку через Spatie Image
     */
    private function executeProcessing(int $fileId, array $operations, ?string $format, int $quality): int
    {
        $filePath = $this->getFilePath($fileId);

        // Загружаем через Spatie
        $spatieImage = SpatieImage::load($filePath);

        // Применяем все операции напрямую к Spatie Image
        foreach ($operations as $operation) {
            if ($operation instanceof SpatieImageOperation) {
                $operation->apply($spatieImage);
            }
        }

        if ($format) {
            $spatieImage->format($format);
        }

        $spatieImage->quality($quality);

        return $this->saveImage($spatieImage, $fileId);
    }

    /**
     * Сохраняет результат обработки в Битрикс
     */
    private function saveImage(SpatieImage $spatieImage, int $originalFileId): int
    {
        $tempFile = $this->createTempFile();

        try {
            $originalFile = $this->getFileArray($originalFileId);
            if (!$originalFile) {
                throw new Main\SystemException("Original file not found: {$originalFileId}");
            }

            $originalFilePath = Main\Loader::getDocumentRoot() . ($originalFile['SRC'] ?? '');
            $originalFormat = $this->detectImageFormat($originalFilePath);

            try {
                $spatieImage->save($tempFile);
            } catch (\Spatie\Image\Exceptions\UnsupportedImageFormat) {
                $spatieImage->format($originalFormat);
                $spatieImage->save($tempFile);
            }

            $tempFormat = $this->detectImageFormat($tempFile);

            $fileArray = FileService::makeFileArray($tempFile);
            if (!$fileArray || !empty($fileArray['error'])) {
                throw new Main\SystemException("Failed to create file array");
            }

            $fileArray['MODULE_ID'] = 'mb.core';
            $fileArray['name'] = $originalFile['ORIGINAL_NAME']
                ? $this->changeExtension($originalFile['ORIGINAL_NAME'], $tempFormat)
                : basename($tempFile);
            $fileArray['description'] = $originalFile['DESCRIPTION'] ?? '';

            $fileId = FileService::saveFile($fileArray, 'image_processor');
            if (!$fileId) {
                throw new Main\SystemException("Failed to save file to database");
            }

            return $fileId;

        } finally {
            try {
                if (Filesystem::instance()->exists($tempFile)) {
                    Filesystem::instance()->delete($tempFile);
                }
            } catch (\Throwable) {
                // Игнорируем ошибки очистки временного файла
            }
        }
    }

    /**
     * Генерирует ключ кэша
     */
    private function generateCacheKey(int $fileId, array $operations, ?string $format, ?int $quality): string
    {
        $filePath = $this->getFilePath($fileId);

        $operationsData = array_map(function($operation) {
            if ($operation instanceof SpatieImageOperation) {
                return [
                    'name' => $operation->getName(),
                    'params' => $operation->getParams()
                ];
            }
            return ['name' => 'unknown', 'params' => []];
        }, $operations);

        $filesystem = Filesystem::instance();

        $data = [
            'file_id' => $fileId,
            'file_mtime' => $filesystem->lastModified($filePath),
            'file_size' => $filesystem->size($filePath),
            'operations' => $operationsData,
            'format' => $format,
            'quality' => $quality,
            'processor' => 'spatie_direct'
        ];

        return md5(serialize($data));
    }

    private function changeExtension(string $filename, string $newExtension): string
    {
        // Убираем точку если есть
        $newExtension = ltrim($newExtension, '.');

        // Метод 1: Используем pathinfo
        $info = pathinfo($filename);

        // Если нет расширения, просто добавляем новое
        if (!isset($info['extension'])) {
            return $filename . '.' . $newExtension;
        }

        // Заменяем расширение
        return ($info['dirname'] ? $info['dirname'] . '/' : '') . $info['filename'] . '.' . $newExtension;

        // Метод 2: Через регулярное выражение (альтернатива)
        // return preg_replace('/\.[^.]+$/', '.' . $newExtension, $filename);
    }

    private function detectImageFormat(string $filePath): string
    {
        if (!function_exists('finfo_open')) {
            return $this->detectFormatBySignature($filePath);
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $filePath);
        finfo_close($finfo);

        return $this->mimeToFormat($mimeType);
    }

    private function detectFormatBySignature(string $filePath): string
    {
        $filesystem = Filesystem::instance();

        if (! $filesystem->exists($filePath) || ! $filesystem->isFile($filePath)) {
            return 'jpg';
        }

        // Читаем только первые байты файла
        try {
            $contents = $filesystem->get($filePath);
        } catch (\Throwable) {
            return 'jpg';
        }

        $header = substr($contents, 0, 12);

        $signatures = [
            'jpg' => "\xFF\xD8\xFF",
            'png' => "\x89PNG\r\n\x1A\n",
            'gif' => 'GIF',
            'webp' => ['RIFF', 8 => 'WEBP'],
            'bmp' => 'BM',
            'avif' => [4 => 'ftypavif'],
        ];

        foreach ($signatures as $format => $signature) {
            if (is_array($signature)) {
                foreach ($signature as $offset => $value) {
                    if (isset($header[$offset]) && strpos($header, $value, $offset) === $offset) {
                        return $format;
                    }
                }
            } elseif (strpos($header, $signature) === 0) {
                return $format;
            }
        }

        return 'jpg';
    }

    /**
     * Конвертирует MIME в формат
     */
    private function mimeToFormat(string $mimeType): string
    {
        $map = [
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/avif' => 'avif',
            'image/bmp' => 'bmp',
            'image/tiff' => 'tiff',
            'image/svg+xml' => 'svg',
        ];

        return $map[$mimeType] ?? 'jpg';
    }

    /**
     * Получает путь к файлу
     */
    private function getFilePath(int $fileId): string
    {
        $file = $this->getFileArray($fileId);

        if (!$file) {
            throw new Main\SystemException("File not found: {$fileId}");
        }

        $filePath = Main\Loader::getDocumentRoot() . $file['SRC'];
        if (!Filesystem::instance()->exists($filePath)) {
            throw new Main\SystemException("File doesn't exist: {$filePath}");
        }

        return $filePath;
    }

    private function getFileArray(int $fileId): array
    {
        if (!$this->fileArray[$fileId]) {
            $this->fileArray[$fileId] = FileService::getFileData($fileId);
        }

        return $this->fileArray[$fileId];
    }

    /**
     * Создает временный файл
     */
    private function createTempFile(): string
    {
        $tempDir = sys_get_temp_dir();
        $filename = 'spatie_' . uniqid('', true) . '.tmp';

        return rtrim($tempDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;
    }
}