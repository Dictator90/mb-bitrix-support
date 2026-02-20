<?php

namespace MB\Bitrix\EntityView\Form;

use MB\Bitrix\Component;

class Parameters extends Component\Parameters\Base
{
    public const INITIAL_MODE_VIEW = 'view';
    public const INITIAL_MODE_EDIT = 'edit';
    public const INITIAL_MODE_INTERMEDIATE = 'intermediate';

    public function __construct(string|int $id)
    {
        parent::__construct();
        $this->setGuId($id);
    }

    /**
     * Идентификатор Формы
     *
     * @param string|int $value
     * @return $this
     */
    public function setGuId(string|int $value): static
    {
        return $this->addParam('GUID', $value);
    }

    /**
     * Начальный режим
     *
     * @see self::INITIAL_MODE_VIEW - Режим просмотра
     * @see self::INITIAL_MODE_EDIT - Режим редактирования
     * @see self::INITIAL_MODE_INTERMEDIATE - Режим промежуточный
     *
     * @param string $value
     * @return $this
     */
    public function setInitialMode(string $value): static
    {
        return $this->addParam('INITIAL_MODE', $value);
    }

    /**
     * Установить адрес отправки формы
     *
     * @param string $value
     * @return $this
     */
    public function setServiceUrl(string $value): static
    {
        return $this->addParam('SERVICE_URL', $value);
    }

    /**
     * Идентифицируемая сущность
     *
     * @param bool $value
     * @return $this
     */
    public function configureIdentifiableEntity(bool $value = true): static
    {
        return $this->addParam('IS_IDENTIFIABLE_ENTITY', $value);
    }

    /**
     * Компонент встраиваемый
     *
     * @param bool $value
     * @return $this
     */
    public function configureEmbeded(bool $value = true): static
    {
        return $this->addParam('IS_EMBEDDED', $value);
    }

    /**
     * Панель инструментов с кнопками
     *
     * @param bool $value
     * @return $this
     */
    public function configureToolPanel(bool $value = true): static
    {
        return $this->addParam('ENABLE_TOOL_PANEL', $value);
    }

    /**
     * Нижняя панель
     *
     * @param bool $value
     * @return $this
     */
    public function configureBottomPanel(bool $value = true): static
    {
        return $this->addParam('ENABLE_TOOL_PANEL', $value);
    }
}
