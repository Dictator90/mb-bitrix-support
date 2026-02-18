<?php
namespace MB\Bitrix\File\Image\Storage;

use Bitrix\Main\FileTable;
use Bitrix\Main\ORM\Fields;
use Bitrix\Main\ORM\Query\Join;
use Bitrix\Main\Type\DateTime;
use MB\Bitrix\Storage\Base;

class CacheTable extends Base
{
    public static function getTableName()
    {
        return 'mb_image_processor_cache';
    }

    public static function getMap()
    {
        return [
            (new Fields\IntegerField('ID'))
                ->configurePrimary()
                ->configureAutocomplete()
            ,
            (new Fields\DatetimeField('TIMESTAMP_X'))
                ->configureDefaultValue(static function () {
                    return new DateTime();
                })
            ,
            (new Fields\IntegerField('ORIGINAL_FILE_ID'))
                ->configureRequired()
                ->configureNullable(false)
            ,
            (new Fields\IntegerField('FILE_ID'))
                ->configureRequired()
                ->configureNullable(false)
            ,
            (new Fields\StringField('CACHE_KEY'))
                ->configureRequired()
                ->configureUnique()
                ->configureNullable(false)
            ,
            new Fields\Relations\Reference(
                'ORIGINAL_FILE',
                FileTable::getEntity(),
                Join::on('this.ORIGINAL_FILE_ID', 'ref.ID')
            ),
            new Fields\Relations\Reference(
                'FILE',
                FileTable::getEntity(),
                Join::on('this.FILE_ID', 'ref.ID')
            ),
        ];
    }

    public static function getIndexes(): array
    {
        return [
            'x_image_processor_file_cache' => ['ORIGINAL_FILE_ID', 'CACHE_KEY']
        ];
    }
}