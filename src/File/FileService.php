<?php

namespace MB\Bitrix\File;

use Bitrix\Main;
use Bitrix\Main\Config\Option;
use Bitrix\Main\File\Image;
use Bitrix\Main\File\Internal\FileDuplicateTable;
use Bitrix\Main\File\Internal\FileHashTable;
use Bitrix\Main\FileTable;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Security;
use Bitrix\Main\Web;
use MB\Bitrix\Filesystem\Filesystem;
use MB\Bitrix\Traits\RememberCachable;

class FileService
{
    use RememberCachable;

    protected const CACHE_DIR = 'b_file';
    protected const UPLOAD_DIR = 'upload';

    /**
     * Сохраняет файл в системе (аналог CFile::SaveFile)
     */
    public static function saveFile(array $fileData, string $savePath, bool $forceRandom = false, bool $skipExtension = false, string $dirAdd = ''): ?int
    {
        $fileData = self::normalizeFileData($fileData);

        if (empty($fileData['name'])) {
            return null;
        }

        // Валидация файла
        if ($error = self::validateFile($fileData)) {
            throw new Main\SystemException($error);
        }

        // Подготовка данных файла
        $preparedData = self::prepareFileData($fileData, $savePath, $forceRandom, $skipExtension, $dirAdd);
        // Проверка дубликатов
        if ($duplicate = self::findDuplicate($preparedData['FILE_SIZE'], $preparedData['FILE_HASH'])) {
            return self::handleDuplicate($duplicate, $preparedData);
        }

        // Сохранение физического файла
        if (!self::savePhysicalFile($preparedData)) {
            return null;
        }

        // Сохранение в БД
        return self::saveToDatabase($preparedData);
    }

    /**
     * Сохраняет несколько файлов за одну операцию
     */
    public static function saveFiles(array $filesData, string $savePath, bool $forceRandom = false): array
    {
        $results = [];

        foreach ($filesData as $key => $fileData) {
            try {
                $fileId = self::saveFile($fileData, $savePath, $forceRandom);
                $results[$key] = [
                    'success' => true,
                    'fileId' => $fileId,
                    'fileData' => $fileId ? self::getFileData($fileId) : null
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
    public static function getFileData(int $fileId): ?array
    {
        return static::remember($fileId, function () use ($fileId) {
            try {
                $file = FileTable::getById($fileId)->fetch();
                self::$cache[$fileId] = $file ? self::enrichFileData($file) : null;
            } catch (\Exception) {
                return null;
            }
        });
    }

    /**
     * Получает данные нескольких файлов за один запрос
     */
    public static function getFilesData(array $fileIds): array
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
            if (isset(self::$cache[$id])) {
                $result[$id] = self::$cache[$id];
            } else {
                $idsToFetch[] = $id;
            }
        }

        if (!empty($idsToFetch)) {
            try {
                $files = FileTable::query()->setSelect(['*', 'HASH.*'])->whereIn('ID', $idsToFetch)->fetchAll();

                foreach ($files as $file) {
                    $enrichedFile = self::enrichFileData($file);
                    self::$cache[$file['ID']] = $enrichedFile;
                    $result[$file['ID']] = $enrichedFile;
                }
            } catch (\Exception $e) {
                // Логирование ошибки
            }
        }

        return $result;
    }

    /**
     * Получает файлы по фильтру с пагинацией
     */
    public static function getFilesByFilter(array $filter = [], array $order = ['ID' => 'DESC'], int $limit = 50, int $offset = 0): Main\ORM\Query\Result
    {
        $query = FileTable::query()
            ->setSelect(['*', 'HASH.*'])
            ->setOrder($order)
            ->setLimit($limit)
            ->setOffset($offset);

        if (!empty($filter)) {
            $query->setFilter(self::normalizeFilter($filter));
        }

        return $query->exec();
    }

    /**
     * Обновляет описание файла
     */
    public static function updateDescription(int $fileId, string $description): bool
    {
        try {
            $connection = Main\Application::getConnection();
            $sqlHelper = $connection->getSqlHelper();
            $descriptionEscaped = $sqlHelper->forSql($description, 255);
            $connection->queryExecute(
                "UPDATE b_file SET DESCRIPTION='{$descriptionEscaped}', TIMESTAMP_X={$sqlHelper->getCurrentDateTimeFunction()} WHERE ID=" . (int)$fileId
            );
            self::cleanCache($fileId);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Удаляет файл
     */
    public static function deleteFile(int $fileId): bool
    {
        try {
            $file = self::getFileData($fileId);
            if (!$file) {
                return false;
            }
            self::deletePhysicalFile($file);

            FileDuplicateTable::markDeleted($fileId);
            FileHashTable::delete($fileId);

            $connection = Main\Application::getConnection();
            $connection->queryExecute("DELETE FROM b_file WHERE ID=" . (int)$fileId);

            self::cleanCache($fileId);

            if (self::isDiskQuotaEnabled()) {
                \CDiskQuota::updateDiskQuota("file", $file['FILE_SIZE'], "delete");
            }

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Возвращает абсолютный путь к файлу
     */
    public static function getFilePath(int $fileId): ?string
    {
        $file = self::getFileData($fileId);
        if (!$file || empty($file['SRC'])) {
            return null;
        }
        return Main\Loader::getDocumentRoot() . $file['SRC'];
    }

    /**
     * Возвращает абсолютный путь к файлу из массива данных
     */
    public static function getFilePathFromArray(array $file): ?string
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
            return self::getFilePath((int)$file['ID']);
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
    public static function makeFileArray(mixed $file, string $source = '', ?string $site = null): ?array
    {
        if ($file === null || $file === '') {
            return ['tmp_name' => '', 'error' => 4]; // UPLOAD_ERR_NO_FILE
        }

        if (is_numeric($file)) {
            $fileData = self::getFileData((int)$file);
            if (!$fileData) {
                return null;
            }

            $uploadDir = Option::get('main', 'upload_dir', 'upload');
            $filePath = $_SERVER['DOCUMENT_ROOT'] . '/' . $uploadDir . '/' . $fileData['SUBDIR'] . '/' . $fileData['FILE_NAME'];

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

                return self::makeFileArray([
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
                'type' => self::getContentType($path),
                'tmp_name' => $virtualFile->getPath(),
                'error' => 0,
            ];
        } else {
            return null;
        }

        foreach (GetModuleEvents('main', 'OnMakeFileArray', true) as $event) {
            $modified = ExecuteModuleEventEx($event, [$file, $source, $site]);
            if (is_array($modified)) {
                $result = array_merge($result, $modified);
            }
        }

        if (!empty($result['tmp_name']) && !empty($result['error'])) {
            return null;
        }

        return $result;
    }

    /**
     * Вспомогательные методы
     */

    protected static function normalizeFileData(array $fileData): array
    {
        if (isset($fileData['content'])) {
            $fileData['size'] = strlen($fileData['content']);
        }

        if (empty($fileData['type'])) {
            $fileData['type'] = self::getContentType($fileData['tmp_name'] ?? '');
        }

        $fileData['ORIGINAL_NAME'] = $fileData['name'] ?? '';
        $fileData['type'] = Web\MimeType::normalize($fileData['type']);

        return $fileData;
    }

    protected static function validateFile(array $fileData): string
    {
        $fileName = self::transformFileName($fileData['name']);

        if (empty($fileName)) {
            return GetMessage("FILE_BAD_FILENAME");
        }

        $io = \CBXVirtualIo::GetInstance();
        if (!$io->ValidateFilenameString($fileName)) {
            return GetMessage("MAIN_BAD_FILENAME1");
        }

        if (mb_strlen($fileName) > 255) {
            return GetMessage("MAIN_BAD_FILENAME_LEN");
        }

        if (\IsFileUnsafe($fileName)) {
            return GetMessage("FILE_BAD_TYPE");
        }

        if (self::isDiskQuotaEnabled()) {
            $quota = new \CDiskQuota();
            if (!$quota->checkDiskQuota($fileData)) {
                return GetMessage("FILE_BAD_QUOTA");
            }
        }

        return "";
    }

    protected static function prepareFileData(array $fileData, string $savePath, bool $forceRandom, bool $skipExtension, string $dirAdd): array
    {
        $fileName = self::transformFileName($fileData['name'], $forceRandom, $skipExtension);
        $filePath = self::generateFilePath($savePath, $fileName, $forceRandom, $dirAdd);

        $imageInfo = self::getImageInfo($fileData['tmp_name']);

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
            'FILE_HASH' => self::calculateFileHash($fileData),
            'EXTERNAL_ID' => $fileData['external_id'] ?? md5(mt_rand()),
            'HANDLER_ID' => $fileData['HANDLER_ID'] ?? '',
            'physical_path' => $filePath['full_path'],
            'tmp_name' => $fileData['tmp_name'] ?? null,
            'content' => $fileData['content'] ?? null,
        ];
    }

    protected static function savePhysicalFile(array &$fileData): bool
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

    protected static function saveToDatabase(array $fileData): int
    {
        $connection = Main\Application::getConnection();
        $sqlHelper = $connection->getSqlHelper();

        $dbFields = [
            'TIMESTAMP_X' => $sqlHelper->getCurrentDateTimeFunction(),
            'MODULE_ID' => $sqlHelper->convertToDbString($fileData['MODULE_ID'], 50),
            'HEIGHT' => intval($fileData['HEIGHT']),
            'WIDTH' => intval($fileData['WIDTH']),
            'FILE_SIZE' => round(floatval($fileData["FILE_SIZE"])),
            'CONTENT_TYPE' => $sqlHelper->convertToDbString($fileData['CONTENT_TYPE'], 255),
            'SUBDIR' => $sqlHelper->convertToDbString($fileData['SUBDIR'], 255),
            'FILE_NAME' => $sqlHelper->convertToDbString($fileData['FILE_NAME'], 255),
            'ORIGINAL_NAME' => $sqlHelper->convertToDbString($fileData['ORIGINAL_NAME'], 255),
            'DESCRIPTION' => $sqlHelper->convertToDbString($fileData['DESCRIPTION'], 255),
            'HANDLER_ID' => $fileData['HANDLER_ID'] ? $sqlHelper->convertToDbString($fileData['HANDLER_ID'], 50) : 'null',
            'EXTERNAL_ID' => $fileData['EXTERNAL_ID'] != "" ? $sqlHelper->convertToDbString($fileData['EXTERNAL_ID'], 50) : 'null',
        ];

        $fields = implode(',', array_keys($dbFields));
        $values = implode(',', array_values($dbFields));

        $connection->queryExecute("INSERT INTO b_file ({$fields}) VALUES ($values)");
        $id = $connection->getInsertedId();
        if ($id <= 0) {
            throw new \Exception("Unable to insert into b_file");
        }

        if ($id && $fileData['FILE_HASH']) {
            FileHashTable::add([
                'FILE_ID' => $id,
                'FILE_SIZE' => $fileData['FILE_SIZE'],
                'FILE_HASH' => $fileData['FILE_HASH'],
            ]);
        }

        self::cleanCache($id);

        if (self::isDiskQuotaEnabled()) {
            \CDiskQuota::updateDiskQuota("file", $fileData['FILE_SIZE'], "insert");
        }

        return $id;
    }

    protected static function findDuplicate(int $fileSize, string $fileHash): ?array
    {
        if (empty($fileHash) || !self::isDuplicateControlEnabled()) {
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

    protected static function handleDuplicate(array $duplicate, array $preparedData): int
    {
        return (int)$duplicate['FILE_ID'];
    }

    protected static function enrichFileData(array $file): array
    {
        $file['SRC'] = self::getFileSrc($file);
        $file['FORMATTED_SIZE'] = self::formatSize($file['FILE_SIZE']);
        $file['IS_IMAGE'] = self::isImage($file['FILE_NAME'], $file['CONTENT_TYPE']);

        return $file;
    }

    protected static function getFileSrc(array $file, bool $external = true): string
    {
        if ($external) {
            foreach (GetModuleEvents('main', 'OnGetFileSRC', true) as $event) {
                if ($src = ExecuteModuleEventEx($event, [$file])) {
                    return $src;
                }
            }
        }

        $uploadDir = Option::get('main', 'upload_dir', self::UPLOAD_DIR);
        $src = '/' . $uploadDir . '/' . $file['SUBDIR'] . '/' . $file['FILE_NAME'];

        return str_replace('//', '/', $src);
    }

    protected static function transformFileName(string $fileName, bool $forceRandom = false, bool $skipExtension = false): string
    {
        $fileName = \GetFileName($fileName);

        $originalName = (!$forceRandom && Option::get("main", "save_original_file_name", "N") == "Y");

        if ($originalName) {
            if (Option::get("main", "translit_original_file_name", "N") == "Y") {
                $fileName = \CUtil::translit($fileName, LANGUAGE_ID, [
                    "max_len" => 1024,
                    "safe_chars" => ".",
                    "replace_space" => '-',
                    "change_case" => false,
                ]);
            }

            if (Option::get("main", "convert_original_file_name", "Y") == "Y") {
                $io = \CBXVirtualIo::GetInstance();
                $fileName = $io->RandomizeInvalidFilename($fileName);
            }
        }

        if (!$skipExtension && strtolower(\GetFileExtension($fileName)) == "jpe") {
            $fileName = substr($fileName, 0, -4) . ".jpg";
        }

        $fileName = \RemoveScriptExtension($fileName);

        if (!$originalName) {
            $ext = $skipExtension ? '' : (\GetFileExtension($fileName) ?: '');
            $fileName = Security\Random::getString(32) . ($ext ? ".{$ext}" : '');
        }

        return $fileName;
    }

    protected static function generateFilePath(string $savePath, string $fileName, bool $forceRandom, string $dirAdd): array
    {
        $uploadDir = Option::get("main", "upload_dir", self::UPLOAD_DIR);
        $io = \CBXVirtualIo::GetInstance();

        if (!$forceRandom && Option::get("main", "save_original_file_name", "N") == "Y") {
            $subdir = $dirAdd ?: self::generateRandomSubdir();
            $fullPath = $savePath . '/' . $subdir;
        } else {
            $subdir = substr(md5($fileName), 0, 3);
            $fullPath = rtrim($savePath, '/') . '/' . $subdir;
        }

        return [
            'subdir' => $fullPath,
            'full_path' => $_SERVER["DOCUMENT_ROOT"] . '/' . $uploadDir . '/' . $fullPath . '/' . $fileName
        ];
    }

    protected static function generateRandomSubdir(): string
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

    protected static function getImageInfo(string $filePath): ?array
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

    protected static function calculateFileHash(array $fileData): string
    {
        if (!self::isDuplicateControlEnabled()) {
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

    protected static function isDuplicateControlEnabled(): bool
    {
        return Option::get('main', 'control_file_duplicates', 'N') === 'Y';
    }

    protected static function isDiskQuotaEnabled(): bool
    {
        return Option::get("main", "disk_space") > 0;
    }

    protected static function cleanCache(int $fileId): void
    {
        unset(self::$cache[$fileId]);

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

    protected static function normalizeFilter(array $filter): array
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

    public static function formatSize(int $size, int $precision = 2): string
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

    public static function isImage(string $filename, string $mimeType = ''): bool
    {
        $ext = strtolower(\GetFileExtension($filename));
        $imageExtensions = explode(",", "jpg,bmp,jpeg,jpe,gif,png,webp");

        if (in_array($ext, $imageExtensions)) {
            return Web\MimeType::isImage($mimeType);
        }

        return false;
    }

    public static function getContentType(string $path): string
    {
        if (function_exists("mime_content_type")) {
            return mime_content_type($path) ?: 'unknown';
        }

        $ext = substr($path, strrpos($path, ".") + 1);
        return Web\MimeType::getByFileExtension($ext) ?: 'unknown';
    }

    protected static function deletePhysicalFile(array $file): void
    {
        $uploadDir = Option::get("main", "upload_dir", self::UPLOAD_DIR);
        $filePath = $_SERVER["DOCUMENT_ROOT"] . "/" . $uploadDir . "/" . $file['SUBDIR'] . "/" . $file['FILE_NAME'];

        try {
            Filesystem::instance()->delete($filePath);
        } catch (\Throwable) {
            // Игнорируем ошибки удаления, как и раньше
        }
    }
}