<?php

namespace MB\Bitrix\Migration\Entities;

use Bitrix\Main;
use Bitrix\Main\Application;
use Bitrix\Main\IO\Directory;
use MB\Bitrix\Migration\Result;
use MB\Bitrix\Migration\BaseEntity;
use MB\Bitrix\Support\Facades\Filesystem as Fs;

class File extends BaseEntity
{
    protected const STR_MODULE_ID = '{{moduleId}}';
    protected const ACTION_COPY_DIR_FILES = 'copy-dir-files';
    protected const ACTION_COPY_DIR = 'copy-dir';

    public function check(): bool
    {
        return true;
    }

    public function up(): Result
    {
        $result = new Result();
        $data = [];

        foreach ($this->iterateOperations() as $operation) {
            if (! $this->sourceExists($operation['from'])) {
                continue;
            }

            $stepResult = $this->applyUpOperation($operation);
            $data[$operation['to']] = $stepResult->isSuccess() ? 'success' : 'error';
            $result->merge($stepResult);
        }

        $result->setData($data);

        return $result;
    }

    public function down(): Result
    {
        $result = new Result();
        $data = [];

        foreach ($this->iterateOperations() as $operation) {
            if (! $this->targetExists($operation['to'])) {
                continue;
            }

            $stepResult = $this->applyDownOperation($operation);
            $data[$operation['to']] = $stepResult->isSuccess() ? 'success' : 'error';
            $result->merge($stepResult);
        }

        $result->setData($data);

        return $result;
    }

    /**
     * @return iterable<int, array{action: string, from: string, to: string}>
     */
    protected function iterateOperations(): iterable
    {
        $modulePath = $this->module->getLocalPath();
        if ($modulePath === null) {
            return [];
        }

        $operations = [];
        foreach ($this->module->getInstallConfig() as $action => $pathDirections) {
            if (! is_array($pathDirections)) {
                continue;
            }

            foreach ($pathDirections as $from => $to) {
                $operations[] = [
                    'action' => (string) $action,
                    'from' => $modulePath . '/install/' . $from,
                    'to' => str_replace(self::STR_MODULE_ID, $this->module->getId(), (string) $to),
                ];
            }
        }

        return $operations;
    }

    /**
     * @param array{action: string, from: string, to: string} $operation
     */
    protected function applyUpOperation(array $operation): Result
    {
        return match ($operation['action']) {
            self::ACTION_COPY_DIR => $this->copyDir($operation['from'], $operation['to']),
            self::ACTION_COPY_DIR_FILES => $this->copyDir($operation['from'], $operation['to'], false, false),
            default => $this->unknownActionResult($operation['action']),
        };
    }

    /**
     * @param array{action: string, from: string, to: string} $operation
     */
    protected function applyDownOperation(array $operation): Result
    {
        return match ($operation['action']) {
            self::ACTION_COPY_DIR => $this->deleteDir($operation['to']),
            self::ACTION_COPY_DIR_FILES => $this->deleteDirFiles($operation['from'], $operation['to']),
            default => $this->unknownActionResult($operation['action']),
        };
    }

    protected function sourceExists(string $fromDir): bool
    {
        return Fs::isDirectory($this->documentRoot() . $fromDir);
    }

    protected function targetExists(string $toDir): bool
    {
        return Fs::isDirectory($this->documentRoot() . $toDir);
    }

    protected function unknownActionResult(string $action): Result
    {
        return (new Result())->addError(new Main\Error("Unknown install action `{$action}`"));
    }

    protected function permissionDeniedResult(string $path): Result
    {
        return (new Result())->addError(new Main\Error(
            sprintf('Path is not writable: %s', $path)
        ));
    }

    protected function copyFailedResult(string $fromDir, string $toDir): Result
    {
        return (new Result())->addError(new Main\Error(
            sprintf('Failed to copy files from `%s` to `%s`.', $fromDir, $toDir)
        ));
    }

    protected function documentRoot(): string
    {
        return Application::getDocumentRoot();
    }

    public function copyDir(string $fromDir, string $toDir, bool $rewrite = true, bool $recursive = true): Result
    {
        $dir = $this->checkDir($toDir);
        if (! is_writable($dir->getPhysicalPath())) {
            return $this->permissionDeniedResult($dir->getPhysicalPath());
        }

        $res = \CopyDirFiles(
            $this->documentRoot() . $fromDir,
            $this->documentRoot() . $toDir,
            $rewrite,
            $recursive,
            false,
            'menu'
        );

        if (! $res) {
            return $this->copyFailedResult($fromDir, $toDir);
        }

        return new Result();
    }

    public function deleteDir(string $dirName): Result
    {
        $dirName = str_replace(array('//', '///'), '/', $this->documentRoot() . '/' . $dirName);

        if (! is_writable($dirName)) {
            return $this->permissionDeniedResult($dirName);
        }

        Directory::deleteDirectory($dirName);

        return new Result();
    }

    public function deleteDirFiles(string $fromDir, string $toDir): Result
    {
        $toDir = str_replace(array('//', '///'), '/', $this->documentRoot() . '/' . $toDir);
        $fromDir = str_replace(array('//', '///'), '/', $this->documentRoot() . '/' . $fromDir);

        if (! is_writable($toDir)) {
            return $this->permissionDeniedResult($toDir);
        }

        DeleteDirFiles($fromDir, $toDir);

        return new Result();
    }

    public function checkDir(string $path): Directory
    {
        if (! Fs::isDirectory($this->documentRoot() . $path)) {
            Fs::makeDirectory($path, 0755, true);
        }

        $dir = new Directory($this->documentRoot() . $path);
        $dir->markWritable();

        return $dir;
    }
}
