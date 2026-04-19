<?php

namespace MB\Bitrix\HighloadBlock;

use MB\Bitrix\Filesystem\Filesystem;
use MB\Bitrix\Migration\BaseEntityManager;
use MB\Bitrix\Migration\Result;

class HighloadBlockManager extends BaseEntityManager
{
    public function getEntityClass(): string
    {
        return Base::class;
    }

    /**
     * Синхронизирует все Highload‑блоки, объявленные как наследники {@see Base},
     * с их схемой (создаёт новые блоки и обновляет UF‑поля).
     */
    public function update(): Result
    {
        return $this->createTable();
    }

    public function deleteAll(): Result
    {
        return $this->dropTable();
    }

    /**
     * Создаёт или обновляет таблицу только для одного HL‑класса.
     *
     * @param class-string<Base> $className
     */
    public function createFor(string $className): Result
    {
        return $this->createTable([$className]);
    }

    /**
     * Удаляет таблицу только для одного HL‑класса.
     *
     * @param class-string<Base> $className
     */
    public function dropFor(string $className): Result
    {
        return $this->dropTableFor([$className]);
    }

    protected function dropTable(): Result
    {
        $result = new Result();
        $libPath = $this->module->getLibPath();
        $classList = $libPath !== null
            ? array_column(
                Filesystem::classFinder()->extends($libPath, $this->getEntityClass()),
                'class'
            )
            : [];

        return $this->dropTableFor($classList, $result);
    }

    /**
     * Внутренний метод удаления таблиц для заданного набора классов.
     *
     * @param string[]    $classList
     * @param Result|null $result
     */
    protected function dropTableFor(array $classList, ?Result $result = null): Result
    {
        $result ??= new Result();

        foreach ($classList as $className) {
            if ($className::getName() && $className::getTableName()) {
                try {
                    $className::getInstance()->dropTable();
                } catch (\Throwable $e) {
                    $result->addError(new \Bitrix\Main\Error($e->getMessage(), $e->getCode()));
                }
            }
        }

        return $result;
    }

    public function createTable(?array $classList = null): Result
    {
        $result = new Result();

        if ($classList === null) {
            $libPath = $this->module->getLibPath();
            $classList = $libPath !== null
                ? array_column(
                    Filesystem::classFinder()->extends($libPath, $this->getEntityClass()),
                    'class'
                )
                : [];
        }

        foreach ($classList as $className) {
            if ($className::getName() && $className::getTableName()) {
                $hl = $className::getInstance();
                if ($hl->isExist()) {
                    $hl->refresh();
                } else {
                    $hl->createTable();
                }

                if ($hl->hasErrors()) {
                    foreach ($hl->getErrorCollection() as $error) {
                        $result->addError($error);
                    }
                }
            }
        }

        return $result;
    }
}
