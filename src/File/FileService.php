<?php

namespace MB\Bitrix\File;

use Bitrix\Main;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Event;
use Bitrix\Main\EventResult;
use Bitrix\Main\File\Image;
use Bitrix\Main\File\Internal\FileDuplicateTable;
use Bitrix\Main\File\Internal\FileHashTable;
use Bitrix\Main\FileTable;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Security;
use Bitrix\Main\Type\DateTime as BitrixDateTime;
use Bitrix\Main\Web;
use MB\Bitrix\Contracts\File\FileServiceContract;
use MB\Bitrix\Foundation\Application;
use MB\Bitrix\Filesystem\Filesystem;

class FileService implements FileServiceContract
{
    /** @var array<int, array|null> строка b_file + обогащение, ключ — ID файла */
    private array $fileDataCache = [];

    protected const CACHE_DIR = 'b_file';
    protected const UPLOAD_DIR = 'upload';

    /**
     * Сохраняет файл в системе (аналог CFile::SaveFile)
     */
    public function saveFile(array $fileData, string $savePath, bool $forceRandom = false, bool $skipExtension = false, string $dirAdd = ''): ?int
    {
        $fileData = $this->normalizeFileData($fileData);

        if (empty($fileData['name'])) {
            return null;
        }

        // Валидация файла
        if ($error = $this->validateFile($fileData)) {
            throw new Main\SystemException($error);
        }

        // Подготовка данных файла
        $preparedData = $this->prepareFileData($fileData, $savePath, $forceRandom, $skipExtension, $dirAdd);
        // Проверка дубликатов
        if ($duplicate = $this->findDuplicate($preparedData['FILE_SIZE'], $preparedData['FILE_HASH'])) {
            return $this->handleDuplicate($duplicate, $preparedData);
        }

        // Сохранение физического файла
        if (!$this->savePhysicalFile($preparedData)) {
            return null;
        }

        // Сохранение в БД
        return $this->saveToDatabase($preparedData);
    }

    /**
     * Короткий алиас {@see saveFile()} — удобно для {@code app('file.service')->save(...)}.
     */
    public function save(
        array $fileData,
        string $savePath,
        bool $forceRandom = false,
        bool $skipExtension = false,
        string $dirAdd = '',
    ): ?int {
        return $this->saveFile($fileData, $savePath, $forceRandom, $skipExtension, $dirAdd);
    }

    /**
     * Сохраняет несколько файлов за одну операцию
     */
    public function saveFiles(array $filesData, string $savePath, bool $forceRandom = false): array
    {
        $results = [];

        foreach ($filesData as $key => $fileData) {
            try {
                $fileId = $this->saveFile($fileData, $savePath, $forceRandom);
                $results[$key] = [
                    'success' => true,
                    'fileId' => $fileId,
                    'fileData' => $fileId ? $this->getFileData($fileId) : null
                ];
            } catch (\Exception $e) {
                $results[$key] = [
                    'success' => false,
                    'error' => $e->getMessage()
                ];
            }
        }

        return $results;
    }

    /**
     * Получает данные одного файла
     */
    public function getFileData(int $fileId): ?array
    {
        if (array_key_exists($fileId, $this->fileDataCache)) {
            return $this->fileDataCache[$fileId];
        }

        try {
            $file = FileTable::getById($fileId)->fetch();
            $enriched = $file ? $this->enrichFileData($file) : null;
            $this->fileDataCache[$fileId] = $enriched;

            return $enriched;
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * Получает данные нескольких файлов за один запрос
     */
    public function getFilesData(array $fileIds): array
    {
        if (empty($fileIds)) {
            return [];
        }

        $fileIds = array_map('intval', $fileIds);
        $fileIds = array_filter($fileIds);

        if (empty($fileIds)) {
            return [];
        }

        $result = [];
        $idsToFetch = [];

        foreach ($fileIds as $id) {
            if (array_key_exists($id, $this->fileDataCache)) {
                $result[$id] = $this->fileDataCache[$id];
            } else {
                $idsToFetch[] = $id;
            }
        }

        if (!empty($idsToFetch)) {
            try {
                $files = FileTable::query()->setSelect(['*', 'HASH.*'])->whereIn('ID', $idsToFetch)->fetchAll();

                foreach ($files as $file) {
                    $enrichedFile = $this->enrichFileData($file);
                    $this->fileDataCache[$file['ID']] = $enrichedFile;
                    $result[$file['ID']] = $enrichedFile;
                }
            } catch (\Exception) {
                // запрошенные ID без ответа БД останутся без ключа до блока ниже
            }

            foreach ($idsToFetch as $id) {
                if (!array_key_exists($id, $result)) {
                    $result[$id] = null;
                }
            }
        }

        return $result;
    }

    /**
     * Получает файлы по фильтру с пагинацией
     */
    public function getFilesByFilter(array $filter = [], array $order = ['ID' => 'DESC'], int $limit = 50, int $offset = 0): Main\ORM\Query\Result
    {
        $query = FileTable::query()
            ->setSelect(['*', 'HASH.*'])
            ->setOrder($order)
            ->setLimit($limit)
            ->setOffset($offset);

        if (!empty($filter)) {
            $query->setFilter($this->normalizeFilter($filter));
        }

        return $query->exec();
    }

    /**
     * Обновляет описание файла
     */
    public function updateDescription(int $fileId, string $description): bool
    {
        try {
            $update = FileTable::update($fileId, [
                'DESCRIPTION' => $description,
                'TIMESTAMP_X' => new BitrixDateTime(),
            ]);
            if (!$update->isSuccess()) {
                return false;
            }
            $this->cleanCache($fileId);

            return true;
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Удаляет файл
     */
    public function deleteFile(int $fileId): bool
    {
        try {
            $file = $this->getFileData($fileId);
            if (!$file) {
                return false;
            }
            $this->deletePhysicalFile($file);

            FileDuplicateTable::markDeleted($fileId);
            FileHashTable::delete($fileId);

            $delete = FileTable::delete($fileId);
            if (!$delete->isSuccess()) {
                return false;
            }

            $this->cleanCache($fileId);

            $this->diskQuotaNotifyDelete((int)$file['FILE_SIZE']);

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Возвращает абсолютный путь к файлу
     */
    public function getFilePath(int $fileId): ?string
    {
        $file = $this->getFileData($fileId);
        if (!$file || empty($file['SRC'])) {
            return null;
        }
        return Main\Loader::getDocumentRoot() . $file['SRC'];
    }

    /**
     * Возвращает абсолютный путь к файлу из массива данных
     */
    public function getFilePathFromArray(array $file): ?string
    {
        if (isset($file['tmp_name']) && $file['tmp_name'] !== '') {
            try {
                if (Filesystem::instance()->exists($file['tmp_name'])) {
                    return $file['tmp_name'];
                }
            } catch (\Throwable) {
                // если абстракция не смогла проверить путь, продолжим дальше
            }
        }
        if (isset($file['SRC']) && $file['SRC'] !== '') {
            return Main\Loader::getDocumentRoot() . $file['SRC'];
        }
        if (isset($file['SUBDIR'], $file['FILE_NAME'])) {
            $uploadDir = Option::get('main', 'upload_dir', self::UPLOAD_DIR);
            $relativePath = '/' . $uploadDir . '/' . $file['SUBDIR'] . '/' . $file['FILE_NAME'];
            return Main\Loader::getDocumentRoot() . str_replace('//', '/', $relativePath);
        }
        if (!empty($file['ID'])) {
            return $this->getFilePath((int)$file['ID']);
        }
        return null;
    }

    /**
     * Преобразует различные форматы данных файла в единый массив-формат,
     * пригодный для использования в методах загрузки файлов.
     *
     * @param mixed $file Может быть ID файла, массивом с данными файла или именем временного файла.
     * @param string $source Источник данных (для обработки через события).
     * @param string|null $site Сайт (не используется напрямую, но может участвовать в событиях).
     * @return array|null Массив с данными файла или null при ошибке.
     */
    public function makeFileArray(mixed $file, string $source = '', ?string $site = null): ?array
    {
        if ($file === null || $file === '') {
            return ['tmp_name' => '', 'error' => 4]; // UPLOAD_ERR_NO_FILE
        }

        if (is_numeric($file)) {
            $fileData = $this->getFileData((int)$file);
            if (!$fileData) {
                return null;
            }

            $uploadDir = Option::get('main', 'upload_dir', 'upload');
            $filePath = $this->getDocumentRoot() . '/' . $uploadDir . '/' . $fileData['SUBDIR'] . '/' . $fileData['FILE_NAME'];

            $result = [
                'name' => $fileData['ORIGINAL_NAME'] ?: $fileData['FILE_NAME'],
                'size' => $fileData['FILE_SIZE'],
                'type' => $fileData['CONTENT_TYPE'],
                'tmp_name' => $filePath,
                'error' => 0,
                'MODULE_ID' => $fileData['MODULE_ID'],
                'description' => $fileData['DESCRIPTION'],
                'external_id' => $fileData['EXTERNAL_ID'],
                'HANDLER_ID' => $fileData['HANDLER_ID'],
            ];
        } elseif (is_array($file)) {
            if (isset($file['tmp_name']) && is_array($file['tmp_name'])) {
                $keys = array_keys($file['tmp_name']);
                $key = reset($keys);

                return $this->makeFileArray([
                    'name' => $file['name'][$key],
                    'type' => $file['type'][$key],
                    'tmp_name' => $file['tmp_name'][$key],
                    'error' => $file['error'][$key],
                    'size' => $file['size'][$key],
                ], $source, $site);
            }

            $result = [
                'name' => $file['name'] ?? '',
                'type' => $file['type'] ?? '',
                'tmp_name' => $file['tmp_name'] ?? '',
                'error' => $file['error'] ?? 0,
                'size' => $file['size'] ?? 0,
            ];

            foreach (['MODULE_ID', 'description', 'external_id', 'HANDLER_ID'] as $field) {
                if (isset($file[$field])) {
                    $result[$field] = $file[$field];
                }
            }
        } elseif (is_string($file)) {
            $path = Main\IO\Path::combine('', $file);
            $virtualFile = new Main\IO\File($path);

            if (!$virtualFile->isExists()) {
                return null;
            }
            
            $result = [
                'name' => $virtualFile->getName(),
                'size' => $virtualFile->getSize(),
                'type' => $this->getContentType($path),
                'tmp_name' => $virtualFile->getPath(),
                'error' => 0,
            ];
        } else {
            return null;
        }

        $result = $this->mergeMainEventParameters('OnMakeFileArray', [$file, $source, $site], $result);

        if (!empty($result['tmp_name']) && !empty($result['error'])) {
            return null;
        }

        return $result;
    }

    /**
     * Вспомогательные методы
     */

    protected function normalizeFileData(array $fileData): array
    {
        if (isset($fileData['content'])) {
            $fileData['size'] = strlen($fileData['content']);
        }

        if (empty($fileData['type'])) {
            $fileData['type'] = $this->getContentType($fileData['tmp_name'] ?? '');
        }

        $fileData['ORIGINAL_NAME'] = $fileData['name'] ?? '';
        $fileData['type'] = Web\MimeType::normalize($fileData['type']);

        return $fileData;
    }

    protected function validateFile(array $fileData): string
    {
        $fileName = $this->transformFileName($fileData['name']);

        if (empty($fileName)) {
            return GetMessage("FILE_BAD_FILENAME");
        }

        if (!$this->validateFilenameString($fileName)) {
            return GetMessage("MAIN_BAD_FILENAME1");
        }

        if (mb_strlen($fileName) > 255) {
            return GetMessage("MAIN_BAD_FILENAME_LEN");
        }

        if ($this->isUnsafeFileName($fileName)) {
            return GetMessage("FILE_BAD_TYPE");
        }

        if (!$this->diskQuotaCheckUpload($fileData)) {
            return GetMessage("FILE_BAD_QUOTA");
        }

        return "";
    }

    protected function prepareFileData(array $fileData, string $savePath, bool $forceRandom, bool $skipExtension, string $dirAdd): array
    {
        $fileName = $this->transformFileName($fileData['name'], $forceRandom, $skipExtension);
        $filePath = $this->generateFilePath($savePath, $fileName, $forceRandom, $dirAdd);

        $imageInfo = $this->getImageInfo($fileData['tmp_name']);

        return [
            'FILE_NAME' => $fileName,
            'ORIGINAL_NAME' => $fileData['ORIGINAL_NAME'],
            'CONTENT_TYPE' => $fileData['type'],
            'FILE_SIZE' => $fileData['size'],
            'SUBDIR' => $filePath['subdir'],
            'MODULE_ID' => $fileData['MODULE_ID'] ?? '',
            'DESCRIPTION' => $fileData['description'] ?? '',
            'WIDTH' => $imageInfo['width'] ?? 0,
            'HEIGHT' => $imageInfo['height'] ?? 0,
            'FILE_HASH' => $this->calculateFileHash($fileData),
            'EXTERNAL_ID' => $fileData['external_id'] ?? md5(mt_rand()),
            'HANDLER_ID' => $fileData['HANDLER_ID'] ?? '',
            'physical_path' => $filePath['full_path'],
            'tmp_name' => $fileData['tmp_name'] ?? null,
            'content' => $fileData['content'] ?? null,
        ];
    }

    protected function savePhysicalFile(array &$fileData): bool
    {
        $filesystem = Filesystem::instance();
        $path = $fileData['physical_path'];

        try {
            if (isset($fileData['content'])) {
                $filesystem->put($path, $fileData['content']);
            } else {
                // Сохраняем поведение move_uploaded_file, при неудаче — копируем через абстракцию
                $tmpPath = $fileData['tmp_name'] ?? null;
                if ($tmpPath === null) {
                    return false;
                }

                // Гарантируем существование директории назначения
                $filesystem->makeDirectory(\dirname($path), 0755, true);

                if (!move_uploaded_file($tmpPath, $path)) {
                    $filesystem->copy($tmpPath, $path);
                }
            }

            @chmod($path, BX_FILE_PERMISSIONS);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    protected function saveToDatabase(array $fileData): int
    {
        $fields = [
            'TIMESTAMP_X' => new BitrixDateTime(),
            'MODULE_ID' => (string)$fileData['MODULE_ID'],
            'HEIGHT' => (int)$fileData['HEIGHT'],
            'WIDTH' => (int)$fileData['WIDTH'],
            'FILE_SIZE' => (int)round((float)$fileData['FILE_SIZE']),
            'CONTENT_TYPE' => (string)$fileData['CONTENT_TYPE'],
            'SUBDIR' => (string)$fileData['SUBDIR'],
            'FILE_NAME' => (string)$fileData['FILE_NAME'],
            'ORIGINAL_NAME' => (string)$fileData['ORIGINAL_NAME'],
            'DESCRIPTION' => (string)$fileData['DESCRIPTION'],
            'HANDLER_ID' => ($fileData['HANDLER_ID'] ?? '') !== '' ? (string)$fileData['HANDLER_ID'] : null,
            'EXTERNAL_ID' => ($fileData['EXTERNAL_ID'] ?? '') !== '' ? (string)$fileData['EXTERNAL_ID'] : null,
        ];

        $add = FileTable::add($fields);
        if (!$add->isSuccess()) {
            throw new Main\SystemException(implode('; ', $add->getErrorMessages()));
        }

        $id = (int)$add->getId();
        if ($id <= 0) {
            throw new Main\SystemException('Unable to insert into b_file');
        }

        if ($id && $fileData['FILE_HASH']) {
            FileHashTable::add([
                'FILE_ID' => $id,
                'FILE_SIZE' => $fileData['FILE_SIZE'],
                'FILE_HASH' => $fileData['FILE_HASH'],
            ]);
        }

        $this->cleanCache($id);

        $this->diskQuotaNotifyInsert((int)$fileData['FILE_SIZE']);

        return $id;
    }

    protected function findDuplicate(int $fileSize, string $fileHash): ?array
    {
        if (empty($fileHash) || !$this->isDuplicateControlEnabled()) {
            return null;
        }

        return FileHashTable::getList([
            'filter' => [
                '=FILE_SIZE' => $fileSize,
                '=FILE_HASH' => $fileHash,
            ],
            'select' => ['FILE_ID', 'FILE.*'],
        ])->fetch();
    }

    protected function handleDuplicate(array $duplicate, array $preparedData): int
    {
        return (int)$duplicate['FILE_ID'];
    }

    protected function enrichFileData(array $file): array
    {
        $file['SRC'] = $this->getFileSrc($file);
        $file['FORMATTED_SIZE'] = $this->formatSize($file['FILE_SIZE']);
        $file['IS_IMAGE'] = $this->isImage($file['FILE_NAME'], $file['CONTENT_TYPE']);

        return $file;
    }

    protected function getFileSrc(array $file, bool $external = true): string
    {
        if ($external) {
            $fromEvent = $this->firstMainEventStringResult('OnGetFileSRC', [$file]);
            if ($fromEvent !== null && $fromEvent !== '') {
                return $fromEvent;
            }
        }

        $uploadDir = Option::get('main', 'upload_dir', self::UPLOAD_DIR);
        $src = '/' . $uploadDir . '/' . $file['SUBDIR'] . '/' . $file['FILE_NAME'];

        return str_replace('//', '/', $src);
    }

    protected function transformFileName(string $fileName, bool $forceRandom = false, bool $skipExtension = false): string
    {
        $fileName = $this->extractBaseFileName($fileName);

        $originalName = (!$forceRandom && Option::get("main", "save_original_file_name", "N") == "Y");

        if ($originalName) {
            if (Option::get("main", "translit_original_file_name", "N") == "Y") {
                $fileName = $this->transliterateFileName($fileName);
            }

            if (Option::get("main", "convert_original_file_name", "Y") == "Y") {
                $fileName = $this->randomizeInvalidFilename($fileName);
            }
        }

        if (!$skipExtension && strtolower($this->extractExtension($fileName)) === 'jpe') {
            $fileName = substr($fileName, 0, -4) . ".jpg";
        }

        $fileName = $this->removeScriptExtensionFromName($fileName);

        if (!$originalName) {
            $ext = $skipExtension ? '' : ($this->extractExtension($fileName) ?: '');
            $fileName = Security\Random::getString(32) . ($ext !== '' ? ".{$ext}" : '');
        }

        return $fileName;
    }

    protected function generateFilePath(string $savePath, string $fileName, bool $forceRandom, string $dirAdd): array
    {
        $uploadDir = Option::get("main", "upload_dir", self::UPLOAD_DIR);

        if (!$forceRandom && Option::get("main", "save_original_file_name", "N") == "Y") {
            $subdir = $dirAdd ?: $this->generateRandomSubdir();
            $fullPath = $savePath . '/' . $subdir;
        } else {
            $subdir = substr(md5($fileName), 0, 3);
            $fullPath = rtrim($savePath, '/') . '/' . $subdir;
        }

        return [
            'subdir' => $fullPath,
            'full_path' => $this->getDocumentRoot() . '/' . $uploadDir . '/' . $fullPath . '/' . $fileName,
        ];
    }

    protected function generateRandomSubdir(): string
    {
        $fylesystem = Filesystem::instance();
        $uploadDir = Option::get("main", "upload_dir", self::UPLOAD_DIR);

        while (true) {
            $random = Security\Random::getString(32);
            $subdir = substr(md5($random), 0, 3) . "/" . $random;

            if (!$fylesystem->existsDirectory("/$uploadDir/$subdir")) {
                return $subdir;
            }
        }
    }

    protected function getImageInfo(string $filePath): ?array
    {
        try {
            $image = new Image($filePath);
            $info = $image->getInfo();

            return $info ? [
                'width' => $info->getWidth(),
                'height' => $info->getHeight()
            ] : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    protected function calculateFileHash(array $fileData): string
    {
        if (!$this->isDuplicateControlEnabled()) {
            return '';
        }

        $maxSize = (int)Option::get('main', 'duplicates_max_size', '100') * 1024 * 1024;

        if ($fileData['size'] > $maxSize && $maxSize !== 0) {
            return '';
        }

        if (isset($fileData['content'])) {
            return hash('md5', $fileData['content']);
        } else {
            return hash_file('md5', $fileData['tmp_name']);
        }
    }

    protected function isDuplicateControlEnabled(): bool
    {
        return Option::get('main', 'control_file_duplicates', 'N') === 'Y';
    }

    protected function isDiskQuotaEnabled(): bool
    {
        return Option::get("main", "disk_space") > 0;
    }

    protected function cleanCache(int $fileId): void
    {
        unset($this->fileDataCache[$fileId]);

        if (defined('CACHED_b_file') && CACHED_b_file !== false) {
            $cache = Main\Application::getInstance()->getManagedCache();
            $bucketSize = (int)(defined('CACHED_b_file_bucket_size') ? CACHED_b_file_bucket_size : 10);
            $bucket = (int)($fileId / $bucketSize);

            $cache->clean(self::CACHE_DIR . '01' . $bucket, self::CACHE_DIR);
            $cache->clean(self::CACHE_DIR . '11' . $bucket, self::CACHE_DIR);
            $cache->clean(self::CACHE_DIR . '00' . $bucket, self::CACHE_DIR);
            $cache->clean(self::CACHE_DIR . '10' . $bucket, self::CACHE_DIR);
        }
    }

    protected function normalizeFilter(array $filter): array
    {
        $normalized = [];
        $allowedFields = [
            'ID', 'MODULE_ID', 'HEIGHT', 'WIDTH', 'CONTENT_TYPE',
            'FILE_NAME', 'ORIGINAL_NAME', 'HANDLER_ID', 'EXTERNAL_ID'
        ];

        foreach ($filter as $key => $value) {
            if (in_array($key, $allowedFields)) {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }

    public function formatSize(int $size, int $precision = 2): string
    {
        $units = ["b", "Kb", "Mb", "Gb", "Tb"];
        $pos = 0;

        while ($size >= 1024 && $pos < 4) {
            $size /= 1024;
            $pos++;
        }

        //todo: refactor
        return round($size, $precision) . " " . Loc::getMessage("FILE_SIZE_" . $units[$pos]);
    }

    public function isImage(string $filename, string $mimeType = ''): bool
    {
        $ext = $this->extractExtension($filename);
        $imageExtensions = explode(",", "jpg,bmp,jpeg,jpe,gif,png,webp");

        if (in_array($ext, $imageExtensions)) {
            return Web\MimeType::isImage($mimeType);
        }

        return false;
    }

    public function getContentType(string $path): string
    {
        if (function_exists("mime_content_type")) {
            return mime_content_type($path) ?: 'unknown';
        }

        $ext = substr($path, strrpos($path, ".") + 1);
        return Web\MimeType::getByFileExtension($ext) ?: 'unknown';
    }

    protected function deletePhysicalFile(array $file): void
    {
        $uploadDir = Option::get("main", "upload_dir", self::UPLOAD_DIR);
        $filePath = $this->getDocumentRoot() . '/' . $uploadDir . '/' . $file['SUBDIR'] . '/' . $file['FILE_NAME'];

        try {
            Filesystem::instance()->delete($filePath);
        } catch (\Throwable) {
            // Игнорируем ошибки удаления, как и раньше
        }
    }

    protected function getDocumentRoot(): string
    {
        return rtrim(Main\Loader::getDocumentRoot(), "/\\");
    }

    /**
     * @param list<mixed> $parameters аргументы события, как у legacy ExecuteModuleEventEx
     * @param array<string, mixed> $base
     * @return array<string, mixed>
     */
    protected function mergeMainEventParameters(string $eventName, array $parameters, array $base): array
    {
        $event = new Event('main', $eventName, $parameters);
        $event->send();

        foreach ($event->getResults() as $eventResult) {
            if ($eventResult->getType() === EventResult::ERROR) {
                continue;
            }
            $params = $eventResult->getParameters();
            if (is_array($params)) {
                $base = array_merge($base, $params);
            }
        }

        return $base;
    }

    /**
     * @param list<mixed> $parameters
     */
    protected function firstMainEventStringResult(string $eventName, array $parameters): ?string
    {
        $event = new Event('main', $eventName, $parameters);
        $event->send();

        foreach ($event->getResults() as $eventResult) {
            if ($eventResult->getType() === EventResult::ERROR) {
                continue;
            }
            $params = $eventResult->getParameters();
            if (is_string($params) && $params !== '') {
                return $params;
            }
            if (is_array($params)) {
                foreach ($params as $value) {
                    if (is_string($value) && $value !== '') {
                        return $value;
                    }
                }
            }
        }

        return null;
    }

    protected function extractBaseFileName(string $pathOrName): string
    {
        $pathOrName = str_replace('\\', '/', $pathOrName);

        return basename($pathOrName);
    }

    protected function extractExtension(string $fileName): string
    {
        $ext = pathinfo($fileName, PATHINFO_EXTENSION);

        return is_string($ext) ? strtolower($ext) : '';
    }

    protected function transliterateFileName(string $fileName): string
    {
        $lang = defined('LANGUAGE_ID') ? (string)constant('LANGUAGE_ID') : 'en';
        $params = [
            'max_len' => 1024,
            'safe_chars' => '.',
            'replace_space' => '-',
            'change_case' => false,
        ];
        if (class_exists(Main\Text\Transliterator::class)) {
            return Main\Text\Transliterator::transliterate($fileName, $lang, $params);
        }

        return $fileName;
    }

    protected function randomizeInvalidFilename(string $fileName): string
    {
        $base = (string)pathinfo($fileName, PATHINFO_FILENAME);
        $ext = (string)pathinfo($fileName, PATHINFO_EXTENSION);
        $safe = preg_replace('/[^\p{L}\p{N}._\-]+/u', '_', $base) ?? '';
        $safe = trim($safe, '._-');
        if ($safe === '') {
            $safe = Security\Random::getString(8);
        }

        return $ext !== '' ? "{$safe}.{$ext}" : $safe;
    }

    protected function removeScriptExtensionFromName(string $fileName): string
    {
        return (string)preg_replace(
            '/\.(php\d*|phtml|php3|php4|php5|pl|cgi|asp|aspx|jsp|shtml|htaccess)$/i',
            '',
            $fileName
        );
    }

    protected function validateFilenameString(string $fileName): bool
    {
        if ($fileName === '' || $fileName === '.' || $fileName === '..') {
            return false;
        }

        return strpbrk($fileName, "<>:\"/\\|?*\x00") === false;
    }

    protected function isUnsafeFileName(string $fileName): bool
    {
        $ext = $this->extractExtension($fileName);
        $unsafe = ['php', 'php3', 'php4', 'php5', 'phtml', 'phar', 'cgi', 'pl', 'asp', 'aspx', 'jsp', 'htaccess', 'htpasswd'];

        return in_array($ext, $unsafe, true);
    }

    /**
     * @internal В публичном API Bitrix по-прежнему используется CDiskQuota; D7-замены с той же семантикой нет.
     */
    protected function diskQuotaCheckUpload(array $fileData): bool
    {
        if (!$this->isDiskQuotaEnabled()) {
            return true;
        }

        return (new \CDiskQuota())->checkDiskQuota($fileData);
    }

    /**
     * @internal См. diskQuotaCheckUpload()
     */
    protected function diskQuotaNotifyInsert(int $fileSize): void
    {
        if (!$this->isDiskQuotaEnabled()) {
            return;
        }

        \CDiskQuota::updateDiskQuota('file', $fileSize, 'insert');
    }

    /**
     * @internal См. diskQuotaCheckUpload()
     */
    protected function diskQuotaNotifyDelete(int $fileSize): void
    {
        if (!$this->isDiskQuotaEnabled()) {
            return;
        }

        \CDiskQuota::updateDiskQuota('file', $fileSize, 'delete');
    }

    /**
     * Resolve from kernel container, or a single fallback when Application is not set.
     */
    public static function resolve(): self
    {
        try {
            return Application::getInstance()->make('file.service');
        } catch (\Throwable) {
            static $fallback = null;

            return $fallback ??= new self();
        }
    }

    /**
     * Back-compat: FileService::method(...) delegates to {@see resolve()}.
     *
     * @param list<mixed> $arguments
     */
    public static function __callStatic(string $name, array $arguments): mixed
    {
        return self::resolve()->$name(...$arguments);
    }
}