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
            'serialNumberAlt' => ['type' => 'string'],
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

        $platformItem = PlatformItem::findByVendorSerial($this->platform->vendor, $this->serialNumber);

        if (true === $this->isNew && ($platformItem instanceof PlatformItem)) {
            throw new Exception('Такой PlatformItem уже существует');
        }

        if (true === $this->isUpdated && ($platformItem instanceof PlatformItem) && ($platformItem->getPk() != $this->getPk())) {
            throw new Exception('Такой PlatformItem уже существует');
        }

        return true;
    }

    /**
     * @param Vendor $vendor
     * @param $serialNumber
     * @return self|bool
     */
    public static function findByVendorSerial(Vendor $vendor, $serialNumber)
    {
        $platformsItems = self::findAllByColumn('serialNumber', $serialNumber);
        $platformsItem = $platformsItems->filter(
            function($platformsItem) use ($vendor) {
                return $platformsItem->platform->vendor->title == $vendor->title;
            }
        )->first();
        if (is_null($platformsItem)) {
            return false;
        } else {
            return $platformsItem;
        }
    }
}
