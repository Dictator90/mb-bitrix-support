<?php

namespace MB\Bitrix\UI\Control\Field;

use Bitrix\Main\Loader;
use MB\Bitrix\UI\Control\Traits\HasName;
use MB\Bitrix\UI\Control\Traits\HasPlaceholder;
use MB\Bitrix\UI\Base\Field;

class HtmlEditorField extends Field\AbstractBaseField
{
    use HasName;
    use HasPlaceholder;

    public function __construct(string $name)
    {
        Loader::includeModule('fileman');

        $this->setName($name);
    }

    public function getHtml(): string
    {
        $editor = new \CHTMLEditor();
        $fieldId = $this->getId() ?: $this->getName();

        $params = array_merge(
            [
                'useFileDialogs' => false,
                'minBodyWidth' => 230,
                'normalBodyWidth' => 530,
                'height' => 200,
                'minBodyHeight' => 200,
                'bAllowPhp' => false,
                'limitPhpAccess' => false,
                'showTaskbars' => false,
                'showNodeNavi' => false,
                'askBeforeUnloadPage' => false,
                'siteId' => SITE_ID,
                'autoResize' => true,
                'autoResizeOffset' => 10,
                'saveOnBlur' => false,
                'setFocusAfterShow' => false,
                'controlsMap' => [
                    ['id' => 'Bold', 'compact' => true, 'sort' => 80],
                    ['id' => 'Italic', 'compact' => true, 'sort' => 90],
                    ['id' => 'Underline', 'compact' => true, 'sort' => 100],
                    ['id' => 'Strikeout', 'compact' => true, 'sort' => 110],
                    ['id' => 'RemoveFormat', 'compact' => true, 'sort' => 120],
                    ['id' => 'Color', 'compact' => true, 'sort' => 130],
                    //['id' => 'FontSelector', 'compact' => false, 'sort' => 135],
                    ['id' => 'FontSize', 'compact' => false, 'sort' => 140],
                    ['separator' => true, 'compact' => false, 'sort' => 145],
                    ['id' => 'OrderedList', 'compact' => true, 'sort' => 150],
                    ['id' => 'UnorderedList', 'compact' => true, 'sort' => 160],
                    ['id' => 'AlignList', 'compact' => false, 'sort' => 190],
                    ['separator' => true, 'compact' => false, 'sort' => 200],
                    ['id' => 'InsertLink', 'compact' => true, 'sort' => 210, 'wrap' => 'bx-b-link-' . $fieldId],
                    ['id' => 'InsertImage', 'compact' => false, 'sort' => 220],
//                    ['id' => 'InsertVideo', 'compact' => true, 'sort' => 230, 'wrap' => 'bx-b-video-' . $fieldId],
                    ['id' => 'InsertTable', 'compact' => false, 'sort' => 250],
                    ['id' => 'Code', 'compact' => true, 'sort' => 260],
                    ['id' => 'Quote', 'compact' => true, 'sort' => 270, 'wrap' => 'bx-b-quote-' . $fieldId],
                    ['id' => 'Smile', 'compact' => false, 'sort' => 280],
                    ['separator' => true, 'compact' => false, 'sort' => 290],
                    ['id' => 'Fullscreen', 'compact' => false, 'sort' => 310],
                    ['id' => 'BbCode', 'compact' => true, 'sort' => 340],
                    ['id' => 'More', 'compact' => true, 'sort' => 400]
                ]

            ],
            [
                'name' => $this->getName(),
                'inputName' => $this->getName(),
                'id' => $fieldId,
                'width' => '100%',
                'placeholder' => $this->getPlaceholder(),
                'content' => $this->getValue(),
                'view' => 'code'
            ]
        );

        ob_start();
        $editor->Show($params);
        return ob_get_clean();
    }
}
