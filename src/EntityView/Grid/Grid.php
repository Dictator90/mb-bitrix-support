<?php

namespace MB\Bitrix\EntityView\Grid;

use Bitrix\Main\Context;
use Bitrix\Main\EventResult;
use Bitrix\Main\Filter\Filter as FilterBase;
use Bitrix\Main\Grid\Column\Editable\CustomConfig;
use Bitrix\Main\Grid\Grid as GridBase;
use Bitrix\Main\Grid\Column\DataProvider as DataProviderBase;
use Bitrix\Main\Grid\Column\Columns as ColumnsBase;
use Bitrix\Main\Grid\Panel\Panel;
use Bitrix\Main\Grid\Row\FieldAssembler;
use Bitrix\Main\Grid\Row\Rows;
use Bitrix\Main\HttpRequest;
use Bitrix\Main\ORM\Entity;
use Bitrix\Main\Web\Uri;
use MB\Bitrix\EntityView\Grid\Column\Columns;
use MB\Bitrix\EntityView\Grid\Row\Action;
use MB\Bitrix\EntityView\Grid\Column\DataProvider;
use MB\Bitrix\EntityView\Grid\Panel\DataProvider as DataProviderPanel;
use MB\Bitrix\EntityView\Filter\Filter;
use MB\Bitrix\Traits\BitrixEventsObservableTrait;

class Grid extends GridBase
{
    use BitrixEventsObservableTrait;

    protected Uri $uri;
    protected HttpRequest $request;

    /**
     * @var DataProvider
     */
    protected DataProvider $dataProvider;

    /**
     * @var DataProviderBase[]|array
     */
    protected array $additionalDataProviders = [];
    protected array $additionalFieldAssemblers = [];

    public function __construct(Entity $entity)
    {
        \CJSCore::init("sidepanel");

        $this->request = Context::getCurrent()->getRequest();
        $this->uri = new Uri($this->request->getRequestUri());

        $settings = new Settings($entity, [
            'CAN_VIEW' => true,
            'CAN_EDIT' => true,
            'CAN_CREATE' => true,
            'EDIT_URL' => '',
        ]);

        $this->attach(config()->getModuleId(), Events::ON_BEFORE_SET_PAGINATION_FILTER);

        parent::__construct($settings);

        $this->dataProvider = new DataProvider($entity, $settings);

        $this->getSettings()->setViewUrl($this->getDefaultViewPath());
        $this->getSettings()->setEditUrl($this->getDefaultEditPath());
        $this->getSettings()->setCreateUrl($this->getDefaultCreatePath());
    }

    public function start()
    {
        $this->buildPagination();
        $this->processRequest();
        return $this;
    }

    public function getEntity()
    {
        return $this->getSettings()->getEntity();
    }

    public function getDataProvider(): DataProvider
    {
        return $this->dataProvider;
    }

    public function setAvailableColumns(array $columns): static
    {
        $this->dataProvider->setColumns($columns);
        return $this;
    }

    /** @deprecated Use setAvailableColumns() */
    public function setAvailableColums(array $columns): static
    {
        return $this->setAvailableColumns($columns);
    }

    public function getAdditionalDataProviders(): array
    {
        return $this->additionalDataProviders;
    }

    public function addAdditionalDataProviders(DataProviderBase ...$dataProviders): static
    {
        foreach ($dataProviders as $dataProvider) {
            $this->additionalDataProviders[] = $dataProvider;
        }

        return $this;
    }
    
    public function getAdditionalFieldAssembler()
    {
        return $this->additionalFieldAssemblers;
    }

    public function addAdditionalFieldAssembler(FieldAssembler ...$fieldAssemblers): static
    {
        foreach ($fieldAssemblers as $fieldAssembler) {
            if (method_exists($fieldAssembler, 'getType')) {
                $type = $fieldAssembler->getType();
                $columsId = $fieldAssembler->getColumnIds();
                foreach ($columsId as $id) {
                    if ($column = $this->getDataProvider()->getColumn($id)) {
                        $column->setType($type);
                        if ($column->isEditable()) {
                            if ($type == 'custom') {
                                $column->setEditable(new CustomConfig($id));
                            } else {
                                $column->setEditable(true);
                            }
                        }
                    }
                }
            }

            $this->additionalFieldAssemblers[] = $fieldAssembler;
        }

        return $this;
    }

    protected function createColumns(): ColumnsBase
    {
        return new Columns($this->dataProvider, ...$this->additionalDataProviders);
    }

    protected function createRows(): Rows
    {
        $rowAssembler = new Row\Assembler\BaseRowAssembler($this->getVisibleColumnsIds(), $this->getEntity());
        if ($this->additionalFieldAssemblers) {
            $rowAssembler->setCustomRowAssemblers($this->additionalFieldAssemblers);
        }

        return new Rows($rowAssembler, new Row\DataProvider($this->getSettings()));
    }

    protected function createPanel(): ?Panel
    {
        return new Panel(new DataProviderPanel($this->getSettings()));
    }

    protected function createFilter(): ?FilterBase
    {
        return new Filter($this->getEntity());
    }

    protected function getDefaultActionPath()
    {

    }

    protected function getDefaultViewPath()
    {
        $this->uri->addParams(['action' => Action\ViewAction::getId()]);
        return $this->prepareOpenPath($this->uri->getUri());
    }

    protected function getDefaultEditPath()
    {
        $this->uri->addParams(['action' => Action\EditAction::getId()]);
        return $this->prepareOpenPath($this->uri->getUri());
    }

    protected function getDefaultCreatePath()
    {
        $this->uri->addParams(['action' => Action\EditAction::getId()]);
        return $this->prepareOpenPath($this->uri->getUri(), true);
    }

    protected function prepareOpenPath(string $path, $setZero = false): string
    {
        $uri = new Uri($path);
        $params = [];
        foreach ($this->getEntity()->getPrimaryArray() as $primary) {
            $params[$primary] = $setZero ? 0 : "#{$primary}#";
        }
        $uri->addParams($params);

        return urldecode($uri->getUri());
    }

    public function buildPagination()
    {
        $filter = $this->getOrmFilter() ?? [];

        $this->beforeSetPaginationFilterProcess($filter);

        $totalCount = $this->getEntity()->getDataClass()::getCount($filter);
        $this->initPagination($totalCount);
        $this->getPagination()->initFromUri();
    }

    protected function sendEvent()
    {

    }

    protected function beforeSetPaginationFilterProcess(array &$filter)
    {
        $this->notify(
            Events::ON_BEFORE_SET_PAGINATION_FILTER,
            [
                'filter' => $filter,
                'entity' => $this
            ],
            function ($results) use (&$filter) {
                foreach ($results as $result) {
                    if ($result->getType() === EventResult::SUCCESS) {
                        $resParams = $result->getParameters();
                        if ($resParams['filter'] && is_array($resParams['filter'])) {
                            $filter = $resParams['filter'];
                        }
                    }
                }
            }
        );
    }
}
