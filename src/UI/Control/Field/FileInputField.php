<?php

namespace MB\Bitrix\UI\Control\Field;

use Bitrix\Main\Context;
use Bitrix\Main\Loader;
use Bitrix\Main\Security\Random;
use Bitrix\Main\UI\FileInput;
use MB\Bitrix\Support\Data\TextString;
use MB\Bitrix\UI\Control\Traits\HasCanDelete;
use MB\Bitrix\UI\Control\Traits\HasCanEdit;
use MB\Bitrix\UI\Control\Traits\HasMaxCount;
use MB\Bitrix\UI\Control\Traits\HasMaxSize;
use MB\Bitrix\UI\Control\Traits\HasMultiple;
use MB\Bitrix\UI\Control\Traits\HasUploadType;
use MB\Bitrix\UI\Control\Traits\HasUseCloud;
use MB\Bitrix\UI\Base\Field;

class FileInputField extends Field\AbstractBaseField
{
    use HasMultiple;
    use HasMaxCount;
    use HasCanEdit;
    use HasCanDelete;
    use HasUseCloud;
    use HasUploadType;
    use HasMaxSize;

    public function __construct(string $name)
    {
        Loader::includeModule('ui');
        $this->setName($name);
    }

    public function getHtml(): string
    {
        $params = [
            "id" => $this->getName() . Random::getString(5),
            "name" => $this->getName() . '[#IND#]',
            "description" => false,
            "upload" => $this->isCanEdit(),
            "allowUpload" => $this->getUploadType(),
            "medialib" => false,
            "fileDialog" => true,
            "cloud" => $this->isUseCloud(),
            "delete" => $this->isCanDelete(),
            "edit" => $this->isCanEdit(),
            "maxCount" => $this->isMultiple() ? $this->getMaxCount() : 1,
            "maxSize" => $this->getMaxSize()
        ];

        if ($this->getUploadType() === FileInput::UPLOAD_EXTENTION_LIST) {
            $params['allowUploadExt'] = $this->getExtensionList();
        }

        return (new FileInput($params))->show($this->getValue());
    }

    public function beforeSave(&$value)
    {
        $result = [];
        $toDelete = [];

        $request = Context::getCurrent()->getRequest();
        if ($delArray = $request->getPost($this->getName() . '_del')) {
            foreach ($delArray as $key => $v) {
                if (TextString::match('#isset_([0-9]+)$#', $key, $match)) {
                    $toDelete[] = $match[1];
                }
            }
        }

        if (is_array($value)) {
            foreach ($value as $i => $image) {
                if (TextString::match('#isset_[0-9]+$#', $i)) {
                    if (in_array($image, $toDelete)) {
                        \CFile::Delete($image);
                        continue;
                    }
                    $result[] = $image;
                    continue;
                }

                $arFile = FileInput::prepareFile($image);
                if (isset($arFile['tmp_name']) && !file_exists($arFile['tmp_name'])) {
                    $tmpFilesDir = \CTempFile::GetAbsoluteRoot();
                    $arFile['tmp_name'] = $tmpFilesDir . $arFile['tmp_name'];
                }

                $result[] = \CFile::SaveFile($arFile, 'modules_files');
            }
        }

        $value = $result;
    }

    protected function beforeSetValue(&$value) {
        $result = [];
        if (is_array($value)) {
            foreach ($value as $i => $v) {
                $result[$this->getName() . '[isset_'.$v.']'] = \CFile::GetFileArray($v);
            }
        } else {
            $result = [$this->getName() . '[isset_'.$value.']' => \CFile::GetFileArray($value)];
        }

        $value = $result;
    }
}
