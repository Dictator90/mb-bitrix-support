<?php

namespace MB\Bitrix\UI\Control\Traits;

use Bitrix\Main\UI\FileInput;

trait HasUploadType
{
    protected string $uploadType = FileInput::UPLOAD_ANY_FILES;
    protected array $extensionList = [];

    public function setImageFileType()
    {
        $this->uploadType = FileInput::UPLOAD_IMAGES;
        return $this;
    }

    public function setAnyFileType()
    {
        $this->uploadType = FileInput::UPLOAD_ANY_FILES;
        return $this;
    }

    public function setExtensionFileType(array $extensions = [])
    {
        $this->uploadType = FileInput::UPLOAD_EXTENTION_LIST;
        $this->setExtensionList($extensions);
        return $this;
    }

    /**
     * ["php", "jpg", "png"]
     *
     * @param array $values
     * @return $this
     */
    public function setExtensionList(array $values)
    {
        $this->extensionList = array_values($values);
        return $this;
    }

    public function getUploadType()
    {
        return $this->uploadType;
    }

    public function getExtensionListRaw()
    {
        return $this->extensionList;
    }

    public function getExtensionList()
    {
        return implode(',', $this->extensionList);
    }

    protected function getUploadTypeArray()
    {
        return [
            FileInput::UPLOAD_ANY_FILES,
            FileInput::UPLOAD_IMAGES,
            FileInput::UPLOAD_EXTENTION_LIST,
        ];
    }
}
