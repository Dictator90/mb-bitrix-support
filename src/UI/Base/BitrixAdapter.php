<?php
namespace MB\Bitrix\UI\Base;

use MB\Bitrix\Contracts\UI\Renderable;
use Bitrix\UI\Contract\Renderable as BitrixRenderable;

class BitrixAdapter implements Renderable
{
    public function __construct(protected BitrixRenderable $renderer)
    {}

    public function render(): void
    {
        echo $this->getHtml();
    }

    public function getHtml(): string
    {
        ob_start();
        $this->renderer->render();
        return (string) ob_get_clean();
    }
}