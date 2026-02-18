<?php

namespace MB\Core\Migration;

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

        $modulePath = $this->module->getLocalPath();
        if ($installConfig = $this->module->getInstallConfig()) {
            foreach ($installConfig as $action => $pathDirections) {
                foreach ($pathDirections as $from => $to) {
                    $from = $modulePath . '/install/' . $from;
                    $to = str_replace(self::STR_MODULE_ID, $this->module->getId(), $to);

                    if (Fs::isDirectory(Application::getDocumentRoot() . $from)) {
                        $res = match ($action) {
                            self::ACTION_COPY_DIR => self::copyDir($from, $to),
                            self::ACTION_COPY_DIR_FILES => self::copyDir($from, $to, false, false),
                            default => new Main\Result()
                        };

                        $resultData = $result->getData();
                        if (!$res->isSuccess()) {
                            $resultData[$to] = 'error';
                            $result->addErrors($res->getErrors());
                        } else {
                            $resultData[$to] = 'success';
                        }

                        $result->setData($resultData);
                    }
                }
            }
        }

        return $result;
    }

    public function down(): Result
    {
        $result = new Result();

        $modulePath = $this->module->getLocalPath();
        if ($installConfig = $this->module->getInstallConfig()) {
            foreach ($installConfig as $action => $pathDirections) {
                foreach ($pathDirections as $from => $to) {
                    $from = $modulePath . '/install/' . $from;
                    $to = str_replace(self::STR_MODULE_ID, $this->module->getId(), $to);
                    if (Fs::isDirectory(Application::getDocumentRoot() . $to)) {
                        $res = match ($action) {
                            self::ACTION_COPY_DIR => self::deleteDir($to),
                            self::ACTION_COPY_DIR_FILES => self::deleteDirFiles($from, $to),
                            default => (new Result())->addError(new Main\Error("Unknown action `$action`"))
                        };

                        $resultData = $result->getData();
                        if (!$res->isSuccess()) {
                            $resultData[$to] = 'error';
                            $result->addErrors($res->getErrors());
                        } else {
                            $resultData[$to] = 'success';
                        }

                        $result->setData($resultData);
                    }
                }
            }
        }

        return $result;
    }

    public static function copyDir($fromDir, $toDir, $rewrite = true, $recursive = true): Result
    {
        $result = new Result();

        $dir = self::checkDir($toDir);
        if (!is_writable($dir->getPhysicalPath())){
            $result->addError(new Main\Error(module('mb.core')->getLang('ERROR_PERMISSIONS', ['#path#' => $dir->getPhysicalPath()])));
            return $result;
        }

        $res = \CopyDirFiles(
            Application::getDocumentRoot() . $fromDir,
            Application::getDocumentRoot() . $toDir,
            $rewrite,
            $recursive,
            false,
            'menu'
        );

        if (!$res) {
            $result->addError(
                new Main\Error(
                    module('mb.core')
                        ->getLang(
                            'ERROR_COPY_DIR_FILES',
                            [
                                '#from#' => $fromDir,
                                '#to#' => $toDir
                            ]

                        )
                )
            );
        }

        return $result;
    }

    public static function deleteDir($dirName)
    {
        $result = new Result();
        $dirName = str_replace(array('//', '///'), '/', Application::getDocumentRoot() . '/' . $dirName);

        if (!is_writable($dirName)){
            return $result->addError(
                new Main\Error(module('mb.core')->getLang('ERROR_PERMISSIONS', ['#path#' => $dirName]))
            );
        }

        Directory::deleteDirectory($dirName);

        return $result;
    }

    public static function deleteDirFiles($fromDir, $toDir)
    {
        $result = new Result();

        $toDir = str_replace(array('//', '///'), '/', Application::getDocumentRoot() . '/' . $toDir);
        $fromDir = str_replace(array('//', '///'), '/', Application::getDocumentRoot() . '/' . $fromDir);

        if (!is_writable($toDir)){
            return $result->addError(
                new Main\Error(module('mb.core')->getLang('ERROR_PERMISSIONS', ['#path#' => $toDir]))
            );
        }

        DeleteDirFiles($fromDir, $toDir);

        return $result;
    }

    public static function checkDir($path)
    {
        if (!Fs::isDirectory(Application::getDocumentRoot() . $path)) {
            Fs::makeDirectory($path, 0755, true);
        }

        $dir = new Directory(Application::getDocumentRoot() . $path);
        $dir->markWritable();

        return $dir;
    }
}
