<?php

namespace MB\Bitrix\EntityView\Grid\Row\Action;

use Bitrix\Main\Grid\Row\Action\BaseAction;
use Bitrix\Main\HttpRequest;
use Bitrix\Main\ORM\Entity;
use Bitrix\Main\Result;
use MB\Bitrix\EntityView\Helper;

class OpenAction extends BaseAction
{
    public function __construct(
        protected Entity $entity,
        protected string $url,
        protected int $width = 1100,
        protected string $loader = 'default-loader'
    ) {}

    public static function getId(): ?string
    {
        return 'open';
    }

    public function processRequest(HttpRequest $request): ?Result
    {
        return null;
    }

    protected function getText(): string
    {
        return 'Открыть';
    }

    public function getControl(array $rawFields): ?array
    {
        $this->default = true;
        $gridId = Helper::getGridIdByEntity($this->entity);
        $url = Helper::getUrlByFields($this->url, $this->entity, $rawFields);
        $jsUrl = \CUtil::JSEscape($url);
        $this->onclick = <<<JS
            BX.SidePanel.Instance.open('$jsUrl', {
                width: {$this->width},
                loader: '{$this->loader}',
                events: {
                    onCloseStart: (event) => {
                        BX.Main.gridManager.getInstanceById('{$gridId}').reload()
                    },
                    onDestroy: (event) => {
                        BX.Main.gridManager.getInstanceById('{$gridId}').reload()
                    },
                    onLoad: (event) => {
                        BX.SidePanel.Instance.bindAnchors({
                            rules:[{
                                condition: [
                                    "/bitrix/admin/userfield_edit.php",
                                    "javascript:void(0)"
                                ],
                            }]
                        });
                    }
                }
            });
JS;
        return parent::getControl($rawFields);
    }
}
