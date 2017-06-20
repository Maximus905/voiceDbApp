<?php

namespace App\Models;

use T4\Core\Exception;
use T4\Core\MultiException;
use T4\Dbal\Query;
use T4\Orm\Model;

/**
 * Class PlatformItem
 * @package App\Models
 *
 * @property string $serialNumber
 * @property string $inventoryNumber
 * @property string $version
 * @property string $details
 * @property string $comment
 *
 * @property Platform $platform
 * @property Appliance $appliance
 */
class PlatformItem extends Model
{
    protected static $schema = [
        'table' => 'equipment.platformItems',
        'columns' => [
            'serialNumber' => ['type' => 'string'],
            'inventoryNumber' => ['type' => 'string'],
            'version' => ['type' => 'string'],
            'details' => ['type' => 'json'],
            'comment' => ['type' => 'string']
        ],
        'relations' => [
            'platform' => ['type' => self::BELONGS_TO, 'model' => Platform::class],
            'appliance' => ['type' => self::HAS_ONE, 'model' => Appliance::class, 'by' => '__platform_item_id']
        ]
    ];

    /**
     * @return bool
     * @throws Exception
     */
    protected function validate()
    {
        if (! ($this->platform instanceof Platform)) {
            throw new Exception('PlatformItem: Неверный тип Platform');
        }

        if (empty(trim($this->serialNumber))) {
            return true;
        }

        $platformItem = PlatformItem::findByPlatformSerial($this->platform, $this->serialNumber);

        if (true === $this->isNew && ($platformItem instanceof PlatformItem)) {
            throw new Exception('Такой PlatformItem уже существует');
        }

        if (true === $this->isUpdated && ($platformItem instanceof PlatformItem) && ($platformItem->getPk() != $this->getPk())) {
            throw new Exception('Такой PlatformItem уже существует');
        }

        return true;
    }

    /**
     * @param Platform $platform
     * @param $serialNumber
     * @return PlatformItem|bool
     */
    public static function findByPlatformSerial(Platform $platform, $serialNumber)
    {
        return $platform->platformItems->filter(
            function ($platformItem) use ($serialNumber) {
                return $serialNumber == $platformItem->serialNumber;
            }
        )->first();
    }
}
