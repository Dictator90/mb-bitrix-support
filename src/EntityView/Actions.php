<?php

namespace MB\Bitrix\EntityView;

enum Actions: string
{
    case ADD = 'addItem';
    case VIEW = 'viewItem';
    case EDIT = 'editItem';
}
