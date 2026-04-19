<?php

namespace MB\Bitrix\Migration;

use Bitrix\Main\Error;
use MB\Bitrix\Contracts;
use MB\Bitrix\Contracts\Migration\Entity as MigrationEntityContract;
use MB\Bitrix\Contracts\Module\Entity as ModuleEntity;
use MB\Bitrix\Migration\Entities\Agent;
use MB\Bitrix\Migration\Entities\Event;
use MB\Bitrix\Migration\Entities\File;
use MB\Bitrix\Migration\Entities\Storage;

final class Facade implements Contracts\Migration\Facade
{
    /**
     * Default module lifecycle pipeline.
     *
     * Files go first on install/update so public/admin assets exist before Bitrix
     * starts using handlers/UI. On uninstall the order is reversed.
     *
     * @var array<string, class-string<MigrationEntityContract>>
     */
    private const DEFAULT_PIPELINE = [
        'file' => File::class,
        'storage' => Storage::class,
        'event' => Event::class,
    ];

    /**
     * Optional entities available outside the default pipeline.
     *
     * @var array<string, class-string<MigrationEntityContract>>
     */
    private const OPTIONAL_PIPELINE = [
        'agent' => Agent::class,
    ];

    /**
     * @param array<string, class-string<MigrationEntityContract>>|null $entities
     * Passing a custom map replaces the built-in registry and is intended for tests
     * or host-module overrides.
     */
    public function __construct(
        protected ModuleEntity $module,
        private ?array $entities = null
    )
    {}

    public function up(): Result
    {
        return $this->upAll();
    }

    public function down(): Result
    {
        return $this->downAll();
    }

    public function upAll(): Result
    {
        return $this->runPipeline($this->defaultPipeline(), 'up');
    }

    public function downAll(): Result
    {
        return $this->runPipeline(array_reverse($this->defaultPipeline(), true), 'down');
    }

    /**
     * @deprecated Don't use -> Need Refactor
     * @return Result
     */
    public function upAgents(): Result
    {
        return $this->runNamedEntity('agent', 'up');
    }

    /**
     * @deprecated Don't use -> Need Refactor
     * @return Result
     */
    public function downAgents(): Result
    {
        return $this->runNamedEntity('agent', 'down');
    }

    /**
     * Переустанавилвает события
     *
     * @return Result
     */
    public function upEvents(): Result
    {
        return $this->runNamedEntity('event', 'up');
    }

    /**
     * Удаляет события
     * @return Result
     */
    public function downEvents(): Result
    {
        return $this->runNamedEntity('event', 'down');
    }

    public function upFiles(): Result
    {
        return $this->runNamedEntity('file', 'up');
    }

    public function downFiles(): Result
    {
        return $this->runNamedEntity('file', 'down');
    }

    public function upStorages(): Result
    {
        return $this->runNamedEntity('storage', 'up');
    }

    public function downStorages(): Result
    {
        return $this->runNamedEntity('storage', 'down');
    }

    /**
     * @return array<string, class-string<MigrationEntityContract>>
     */
    private function defaultPipeline(): array
    {
        if ($this->entities === null) {
            return self::DEFAULT_PIPELINE;
        }

        $pipeline = [];
        foreach (array_keys(self::DEFAULT_PIPELINE) as $name) {
            if (isset($this->entities[$name])) {
                $pipeline[$name] = $this->entities[$name];
            }
        }

        return $pipeline;
    }

    /**
     * @return array<string, class-string<MigrationEntityContract>>
     */
    private function allEntities(): array
    {
        return $this->entities ?? (self::DEFAULT_PIPELINE + self::OPTIONAL_PIPELINE);
    }

    private function runNamedEntity(string $name, string $method): Result
    {
        $entities = $this->allEntities();

        if (! isset($entities[$name])) {
            return (new Result())->addError(new Error("Invalid migration entity `{$name}`"));
        }

        return $this->callEntity($entities[$name], $method);
    }

    /**
     * @param array<string, class-string<MigrationEntityContract>> $pipeline
     */
    private function runPipeline(array $pipeline, string $method): Result
    {
        $result = new Result();
        $data = [];

        foreach ($pipeline as $name => $className) {
            $stepResult = $this->callEntity($className, $method);
            $data[$name] = $stepResult;
            $result->merge($stepResult);
        }

        $result->setData($data);

        return $result;
    }

    private function callEntity(string $className, string $method, mixed $arguments = null): Result
    {
        $result = new Result();
        $reflection = new \ReflectionClass($className);

        if (!$reflection->implementsInterface(MigrationEntityContract::class)) {
            $result->addError(new Error('Class must implement ' . MigrationEntityContract::class));
        } elseif (! $reflection->hasMethod($method)) {
            $result->addError(new Error("Class {$className} hasn't called method {$method}"));
        } else {
            /** @var MigrationEntityContract $instance */
            $instance = $reflection->newInstance($this->module);
            try {
                return $arguments === null
                    ? $instance->{$method}()
                    : $instance->{$method}($arguments);
            } catch (\Throwable $throwable) {
                $result->addThrowable($throwable);
            }
        }

        return $result;
    }
}
