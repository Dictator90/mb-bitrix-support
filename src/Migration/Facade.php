<?php

namespace MB\Bitrix\Migration;

use Bitrix\Main\Error;
use MB\Bitrix\Contracts\Module\Entity as ModuleEntity;
use MB\Bitrix\Contracts;

final class Facade implements Contracts\Migration\Facade
{
    public function __construct(protected ModuleEntity $module)
    {}

    public function upAll(): Result
    {
        $result = new Result();

        $result->setData([
            'file' => $this->upFiles(),
            'storage' => $this->upStorages(),
            'event' => $this->upEvents(),
            //'agent' => $this->upAgent(),
        ]);

        return $result;
    }

    public function downAll(): Result
    {
        $result = new Result();

        $result->setData([
            'file' => $this->downFiles(),
            'storage' => $this->downStorages(),
            'event' => $this->downEvents(),
            //'agent' => $this->downAgent(),
        ]);

        return $result;
    }

    public function upStorages(): Result
    {
        return $this->up(Storage::class);
    }

    public function downStorages(): Result
    {
        return $this->down(Storage::class);
    }

    /**
     * @deprecated Don't use -> Need Refactor
     * @return Result
     */
    public function upAgents(): Result
    {
        return $this->up(Agent::class);
    }

    /**
     * @deprecated Don't use -> Need Refactor
     * @return Result
     */
    public function downAgents(): Result
    {
        return $this->down(Agent::class);
    }

    /**
     * Переустанавилвает события
     *
     * @return Result
     */
    public function upEvents(): Result
    {
        return $this->up(Event::class);
    }

    /**
     * Удаляет события
     * @return Result
     */
    public function downEvents(): Result
    {
        return $this->down(Event::class);
    }

    public function upFiles(): Result
    {
        return $this->up(File::class);
    }

    public function downFiles(): Result
    {
        return $this->down(File::class);
    }

    protected function up(string $className, $arguments = null)
    {
        return $this->callEntity($className, 'up', $arguments);
    }

    protected function down(string $className, $arguments = null)
    {
        return $this->callEntity($className, 'down', $arguments);
    }

    protected function check(string $className, $arguments = null)
    {
        return $this->callEntity($className, 'check', $arguments);
    }

    protected function callEntity($className, $method, $arguments = null)
    {
        $result = new Result();
        if (in_array($className, static::getEntityList())) {
            $result = $this->callMethod($className, $method, $arguments);
        } else {
            $result->addError(new Error("Invalid entity class `{$className}`"));
        }

        return $result;
    }

    protected function callMethod($className, $method, $arguments = null)
    {
        $result = new Result();
        $reflection = new \ReflectionClass($className);

        if (!$reflection->implementsInterface(Contract\MigrationInterface::class)) {
            $result->addError(new Error("Class must be implements " . Contract\MigrationInterface::class));
        } elseif ($reflection->hasMethod($method)) {
            /** @var Contract\MigrationInterface $instancse */
            $instance = $reflection->newInstance($this->module);
            return $instance->{$method}($arguments);
        } else {
            $result->addError(new Error("Class {$className} hasn't called method {$method}"));
        }

        return $result;
    }

    protected static function getEntityList(): array
    {
        return [
            Storage::class,
            Agent::class,
            Event::class,
            File::class,
        ];
    }
}
