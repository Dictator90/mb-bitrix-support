<?php

namespace MB\Bitrix\File\Image;

use Bitrix\Main;
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
    /**
     * Конструктор инициализирует таблицу кэша
     */
    public function __construct()
    {
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
            ? FileService::getFilePath($file)
            : FileService::getFilePathFromArray($file);
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
        $connection = Main\Application::getConnection();

        if (!$connection->isTableExists(CacheTable::getTableName())) {
            CacheTable::getEntity()->createDbTable();
        }
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
}