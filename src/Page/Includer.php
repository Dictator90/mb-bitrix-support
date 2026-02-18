<?php

namespace MB\Bitrix\Page;

use Bitrix\Main\Application;
use MB\Bitrix\Support\Facades\Filesystem as Fs;

class Includer
{
    public static function mainInclude(string $relFilePath, $langId = null, $parentComponent = null, $hideIcons = false)
    {
        global $APPLICATION;

        $rawFilePath = $relFilePath;
        if ($langId) {
            $file = new \Bitrix\Main\IO\File($relFilePath);
            $fileName = $file->getName();
            if ($file->getExtension()) {
                $fileName = str_replace('.' . $file->getExtension(), '', $file->getName());
            }

            $fileName = $fileName . '_' . $langId;
            $relFilePath =  $file->getDirectoryName() . "/" . $fileName . ($file->getExtension() ? '.' . $file->getExtension() : '');
        }


        $relFilePath = self::checkPath($relFilePath);
        $file = new \Bitrix\Main\IO\File(Application::getDocumentRoot() . $relFilePath);
        if (!$file->isFile() || !$file->isExists()) {
            return;
        }

        $APPLICATION->IncludeComponent(
            'bitrix:main.include',
            '',
            [
                'AREA_FILE_SHOW' => 'file',
                'PATH' => $relFilePath,
            ],
            $parentComponent,
            ['HIDE_ICONS' => !!$hideIcons ? 'Y' : 'N']
        );
    }

    public static function checkPath(string $rel_path, $returnRelativePath = true)
    {
        $path = $rel_path;
        $docRoot = Application::getDocumentRoot();

        if (!str_starts_with($rel_path, "/")) {
            $templatePath = SITE_TEMPLATE_PATH . '/' . $rel_path;
            $defaultTemplatePath = BX_PERSONAL_ROOT . '/templates/.default/' . $rel_path;

            if (Fs::exists($templatePath)) {
                $path = $templatePath;
            } elseif (Fs::exists($defaultTemplatePath)) {
                $path = $defaultTemplatePath;
            } else {
                $path = '/' . $rel_path;
            }
        }

        return $returnRelativePath ? $path : $docRoot . str_replace($docRoot, '', $path);

    }
}