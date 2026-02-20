<?php

namespace MB\Bitrix\Contracts\UI;

/**
 * Contract for UI components that can output HTML.
 *
 * Implementations should follow: render() outputs the same content as getHtml() returns.
 * Default implementation: render(): void { echo $this->getHtml(); }
 * Use RendersWithConditions trait when condition checks and before/after hooks are needed.
 */
interface Renderable
{
    /** Output the component HTML to the current output stream. */
    public function render(): void;

    /** Return the component HTML as a string (no side effects). */
    public function getHtml(): string;
}
