<?php

namespace MB\Bitrix\UI\Base;

use MB\Bitrix\UI\Base\Form\Base as FormBase;

/**
 * Holds a collection of Form\Base instances. Use when you need to manage multiple forms
 * without tying that responsibility to a CSS View.
 */
class FormContainer
{
    /** @var FormBase[] */
    protected array $forms = [];

    public function __construct(array $forms = [])
    {
        $this->forms = $forms;
    }

    /**
     * @param FormBase[] $forms
     * @return static
     */
    public function setForms(array $forms): static
    {
        $this->forms = $forms;
        return $this;
    }

    public function addForm(FormBase $form): static
    {
        $this->forms[] = $form;
        return $this;
    }

    /**
     * @return FormBase[]
     */
    public function getForms(): array
    {
        return $this->forms;
    }
}
