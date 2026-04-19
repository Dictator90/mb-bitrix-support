<?php

declare(strict_types=1);

namespace MB\Bitrix\Migration;

use Bitrix\Main\Application;
use MB\Bitrix\Filesystem\Filesystem;
use MB\Bitrix\Storage\Base as StorageBase;

/**
 * Syncs module {@see StorageBase} ORM tables (create / migrate fields).
 */
class StorageEntityManager extends BaseEntityManager
{
    public function getEntityClass(): string
    {
        return StorageBase::class;
    }

    public function update(): Result
    {
        $result = new Result();
        $connection = Application::getConnection();
        $libPath = $this->module->getLibPath();
        $classes = $libPath !== null
            ? array_column(
                Filesystem::classFinder()->extends($libPath, $this->getEntityClass()),
                'class'
            )
            : [];

        foreach ($classes as $className) {
            if (! is_subclass_of($className, StorageBase::class)) {
                continue;
            }
            $partial = $className::migrate($connection);
            if (! $partial->isSuccess()) {
                $result->addErrors($partial->getErrors());
            }
        }

        return $result;
    }

    public function deleteAll(): Result
    {
        $result = new Result();
        $connection = Application::getConnection();
        $libPath = $this->module->getLibPath();
        $classes = $libPath !== null
            ? array_column(
                Filesystem::classFinder()->extends($libPath, $this->getEntityClass()),
                'class'
            )
            : [];

        foreach ($classes as $className) {
            if (! is_subclass_of($className, StorageBase::class)) {
                continue;
            }
            try {
                $connection->dropTable($className::getTableName());
            } catch (\Throwable $e) {
                $result->addThrowable($e);
            }
        }

        return $result;
    }
}
