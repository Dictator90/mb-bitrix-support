<?php

declare(strict_types=1);

namespace MB\Bitrix\File;

use Bitrix\Main;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Event;
use Bitrix\Main\EventResult;
use Bitrix\Main\File\Image;
use Bitrix\Main\File\Internal\FileDuplicateTable;
use Bitrix\Main\File\Internal\FileHashTable;
use Bitrix\Main\FileTable;
use Bitrix\Main\Security;
use Bitrix\Main\Type\DateTime as BitrixDateTime;
use Bitrix\Main\Web;
use MB\Bitrix\Bitrix\Adapters\ApplicationAdapter;
use MB\Bitrix\Bitrix\Adapters\LocalizationAdapter;
use MB\Bitrix\Bitrix\Adapters\QuotaAdapter;
use MB\Bitrix\Contracts\Bitrix\ApplicationAdapter as ApplicationAdapterContract;
use MB\Bitrix\Contracts\Bitrix\LocalizationAdapter as LocalizationAdapterContract;
use MB\Bitrix\Contracts\Bitrix\QuotaAdapter as QuotaAdapterContract;
use MB\Bitrix\Contracts\File\DuplicateResolver as DuplicateResolverContract;
use MB\Bitrix\Contracts\File\FileRepository as FileRepositoryContract;
use MB\Bitrix\Contracts\File\FileServiceContract;
use MB\Bitrix\Contracts\File\MetadataReader as MetadataReaderContract;
use MB\Bitrix\Contracts\File\Uploader as UploaderContract;
use MB\Bitrix\File\Services\DuplicateResolver;
use MB\Bitrix\File\Services\FileRepository;
use MB\Bitrix\File\Services\MetadataReader;
use MB\Bitrix\File\Services\Uploader;
use MB\Bitrix\Filesystem\Filesystem;

class FileService implements FileServiceContract
{
    /** @var array<int, array|null> строка b_file + обогащение, ключ — ID файла */
    private array $fileDataCache = [];

    protected const CACHE_DIR = 'b_file';
    protected const UPLOAD_DIR = 'upload';

    private readonly UploaderContract $uploader;
    private readonly DuplicateResolverContract $duplicateResolver;
    private readonly MetadataReaderContract $metadataReader;
    private readonly FileRepositoryContract $fileRepository;
    private readonly ApplicationAdapterContract $applicationAdapter;
    private readonly QuotaAdapterContract $quotaAdapter;
    private readonly LocalizationAdapterContract $localizationAdapter;

    public function __construct(
        ?UploaderContract $uploader = null,
        ?DuplicateResolverContract $duplicateResolver = null,
        ?MetadataReaderContract $metadataReader = null,
        ?FileRepositoryContract $fileRepository = null,
        ?ApplicationAdapterContract $applicationAdapter = null,
        ?QuotaAdapterContract $quotaAdapter = null,
        ?LocalizationAdapterContract $localizationAdapter = null,
    ) {
        $this->applicationAdapter = $applicationAdapter ?? new ApplicationAdapter();
        $this->uploader = $uploader ?? new Uploader($this->applicationAdapter);
        $this->duplicateResolver = $duplicateResolver ?? new DuplicateResolver();
        $this->metadataReader = $metadataReader ?? new MetadataReader();
        $this->fileRepository = $fileRepository ?? new FileRepository();
        $this->quotaAdapter = $quotaAdapter ?? new QuotaAdapter();
        $this->localizationAdapter = $localizationAdapter ?? new LocalizationAdapter();
    }

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
        if ($duplicate = $this->duplicateResolver->find($preparedData['FILE_SIZE'], $preparedData['FILE_HASH'])) {
            return $this->handleDuplicate($duplicate, $preparedData);
        }

        // Сохранение физического файла
        if (!$this->uploader->save($preparedData)) {
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
        return $this->applicationAdapter->getDocumentRoot() . $file['SRC'];
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
            }
        }
        if (isset($file['SRC']) && $file['SRC'] !== '') {
            return $this->applicationAdapter->getDocumentRoot() . $file['SRC'];
        }
        if (isset($file['SUBDIR'], $file['FILE_NAME'])) {
            $uploadDir = Option::get('main', 'upload_dir', self::UPLOAD_DIR);
            $relativePath = '/' . $uploadDir . '/' . $file['SUBDIR'] . '/' . $file['FILE_NAME'];
            return $this->applicationAdapter->getDocumentRoot() . str_replace('//', '/', $relativePath);
        }
        if (!empty($file['ID'])) {
            return $this->getFilePath((int)$file['ID']);
        }
        return null;
    }

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
            return $this->localizationAdapter->message('FILE_BAD_FILENAME', 'Invalid file name');
        }

        if (!$this->validateFilenameString($fileName)) {
            return $this->localizationAdapter->message('MAIN_BAD_FILENAME1', 'Invalid file name');
        }

        if (mb_strlen($fileName) > 255) {
            return $this->localizationAdapter->message('MAIN_BAD_FILENAME_LEN', 'File name is too long');
        }

        if ($this->isUnsafeFileName($fileName)) {
            return $this->localizationAdapter->message('FILE_BAD_TYPE', 'Unsafe file type');
        }

        if (!$this->diskQuotaCheckUpload($fileData)) {
            return $this->localizationAdapter->message('FILE_BAD_QUOTA', 'Disk quota exceeded');
        }

        return "";
    }

    protected function prepareFileData(array $fileData, string $savePath, bool $forceRandom, bool $skipExtension, string $dirAdd): array
    {
        $fileName = $this->transformFileName($fileData['name'], $forceRandom, $skipExtension);
        $filePath = $this->generateFilePath($savePath, $fileName, $forceRandom, $dirAdd);

        $imageInfo = $this->metadataReader->imageInfo((string) ($fileData['tmp_name'] ?? ''));

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
            'FILE_HASH' => $this->metadataReader->hash($fileData),
            'EXTERNAL_ID' => $fileData['external_id'] ?? md5((string) mt_rand()),
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
        return $this->duplicateResolver->resolve($duplicate, $preparedData);
    }

    protected function enrichFileData(array $file): array
    {
        $file['SRC'] = $this->getFileSrc($file);
        $file['FORMATTED_SIZE'] = $this->formatSize((int)$file['FILE_SIZE']);
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
        $bucketSize = (int)(defined('CACHED_b_file_bucket_size') ? CACHED_b_file_bucket_size : 10);
        $this->applicationAdapter->clearManagedFileCache($fileId, self::CACHE_DIR, $bucketSize);
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
        return $this->metadataReader->formatSize($size, $precision);
    }

    public function isImage(string $filename, string $mimeType = ''): bool
    {
        return $this->metadataReader->isImage($filename, $mimeType);
    }

    public function getContentType(string $path): string
    {
        return $this->metadataReader->contentType($path);
    }

    protected function deletePhysicalFile(array $file): void
    {
        $this->uploader->delete($file);
    }

    protected function getDocumentRoot(): string
    {
        return $this->applicationAdapter->getDocumentRoot();
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
        return $this->quotaAdapter->checkUpload($fileData);
    }

    /**
     * @internal См. diskQuotaCheckUpload()
     */
    protected function diskQuotaNotifyInsert(int $fileSize): void
    {
        $this->quotaAdapter->notifyInsert($fileSize);
    }

    /**
     * @internal См. diskQuotaCheckUpload()
     */
    protected function diskQuotaNotifyDelete(int $fileSize): void
    {
        $this->quotaAdapter->notifyDelete($fileSize);
    }

}
