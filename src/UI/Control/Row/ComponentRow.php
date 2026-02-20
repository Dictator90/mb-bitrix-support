<?php

namespace MB\Bitrix\UI\Control\Row;

use MB\Bitrix\UI\Base\Row\Base as RowBase;

class ComponentRow extends RowBase
{
    public function __construct(
        protected string $namespace,
        protected string $template = '',
        protected array $params = []
    )
    {}

    protected function getComponentContent(): false|string
    {
        global $APPLICATION;
        ob_start();
        $APPLICATION->IncludeComponent(
            $this->namespace,
            $this->template,
            $this->params
        );
        return ob_get_clean();
    }

    public function getHtml(): string
    {
        return <<<DOC
        <div class="ui-form-row">
            <div class="ui-form-content">
                {$this->getComponentContent()}
            </div>
        </div>
DOC;
    }
}
