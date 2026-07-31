<?php

declare(strict_types=1);

namespace MB\Bitrix\File\Image;

use Bitrix\Main;
use MB\Bitrix\Contracts\File\FileServiceContract;
use MB\Bitrix\Contracts\File\ImageCache;
use MB\Bitrix\File\FileService;
use MB\Bitrix\File\Image\Storage\CacheTable;
use MB\Bitrix\Filesystem\Filesystem;

/**
 * Реализация кэширования результатов обработки изображений в БД
 * @package Bitrix\Main\File\Image
 */
class DatabaseImageCache implements ImageCache
{
    /** Проверка существования таблицы кэша выполняется один раз на процесс. */
    private static bool $tableChecked = false;

    private FileServiceContract $files;

    /**
     * Конструктор инициализирует таблицу кэша
     */
    public function __construct(?FileServiceContract $files = null)
    {
        $this->files = $files ?? $this->resolveFileService();
        $this->initTable();
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $key): ?int
    {
        $object =
            CacheTable::query()
                ->addSelect('ID')
                ->addSelect('FILE_ID')
                ->addSelect('FILE')
                ->where('CACHE_KEY', $key)
                ->fetchObject()
        ;

        if ($object) {
            $file = $object->get('FILE')?->collectValues(recursive: true);
            if ($file && $this->fileExists($file)) {
                return $object->getFileId();
            } else {
                $object->delete();
                $object->save();
            }
        }

        return null;
    }

    /**
     * Пакетный аналог {@see get()}: возвращает валидные соответствия «ключ → ID файла»
     * одним запросом вместо запроса на каждый ключ. Записи с отсутствующим на диске
     * итоговым файлом удаляются (как в get()), чтобы последующая переобработка смогла
     * записать кэш по тому же ключу.
     *
     * @param string[] $keys
     * @return array<string, int>
     */
    public function getMany(array $keys): array
    {
        $keys = array_values(array_unique(array_filter($keys, static fn ($k) => $k !== '')));
        if ($keys === []) {
            return [];
        }

        $objects = CacheTable::query()
            ->addSelect('ID')
            ->addSelect('CACHE_KEY')
            ->addSelect('FILE_ID')
            ->addSelect('FILE')
            ->whereIn('CACHE_KEY', $keys)
            ->fetchCollection();

        $result = [];
        $staleKeys = [];
        foreach ($objects as $object) {
            $file = $object->get('FILE')?->collectValues(recursive: true);
            if ($file && $this->fileExists($file)) {
                $result[$object->getCacheKey()] = $object->getFileId();
            } else {
                $staleKeys[] = $object->getCacheKey();
            }
        }

        if ($staleKeys !== []) {
            $this->deleteMany($staleKeys);
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function set(string $key, int $fileId, int $originalFileId): Main\Entity\AddResult
    {
        return CacheTable::add([
           'ORIGINAL_FILE_ID' => $originalFileId,
           'FILE_ID' => $fileId,
           'CACHE_KEY' => $key,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function clearForFile(int $fileId): void
    {
        $connection = Main\Application::getConnection();
        $helper = $connection->getSqlHelper();
        $connection->query("DELETE FROM {$helper->forSql(CacheTable::getTableName())} WHERE ORIGINAL_FILE_ID = {$helper->forSql($fileId)}");
    }

    /**
     * Проверяет существование файла в файловой системе
     * @param int|array $file ID файла или массив с данными файла
     * @return bool
     */
    private function fileExists(int|array $file): bool
    {
        $filePath = is_int($file)
            ? $this->files->getFilePath($file)
            : $this->files->getFilePathFromArray($file);
        if ($filePath === null) {
            return false;
        }

        return Filesystem::instance()->exists($filePath);
    }

    /**
     * Инициализирует таблицу кэша в БД
     */
    private function initTable(): void
    {
        if (self::$tableChecked) {
            return;
        }

        $connection = Main\Application::getConnection();

        if (!$connection->isTableExists(CacheTable::getTableName())) {
            CacheTable::getEntity()->createDbTable();
        }

        self::$tableChecked = true;
    }

    /**
     * Удаляет запись из кэша
     * @param string $key Ключ кэша
     */
    private function delete(string $key): void
    {
        $connection = Main\Application::getConnection();
        $helper = $connection->getSqlHelper();
        $connection->query("DELETE FROM {$helper->forSql(CacheTable::getTableName())} WHERE CACHE_KEY = '{$helper->forSql($key)}'");
    }

    /**
     * @param string[] $keys
     */
    private function deleteMany(array $keys): void
    {
        if ($keys === []) {
            return;
        }

        $connection = Main\Application::getConnection();
        $helper = $connection->getSqlHelper();
        $escaped = array_map(static fn ($k) => "'" . $helper->forSql($k) . "'", $keys);
        $connection->query(
            'DELETE FROM ' . $helper->forSql(CacheTable::getTableName())
            . ' WHERE CACHE_KEY IN (' . implode(',', $escaped) . ')'
        );
    }

    private function resolveFileService(): FileServiceContract
    {
        try {
            /** @var FileServiceContract $service */
            $service = app('file.service');

            return $service;
        } catch (\Throwable) {
            return new FileService();
        }
    }
}
