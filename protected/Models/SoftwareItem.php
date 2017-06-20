<?php

namespace App\Models;

use T4\Core\Collection;
use T4\Core\Exception;
use T4\Core\MultiException;
use T4\Dbal\Query;
use T4\Orm\Model;

/**
 * Class SoftwareItem
 * @package App\Models
 *
 * @property string $version
 * @property string $details
 * @property string $comment
 *
 * @property Software $software
 * @property Collection|Appliance[] $appliances
 */
class SoftwareItem extends Model
{
    protected static $schema = [
        'table' => 'equipment.softwareItems',
        'columns' => [
            'version' => ['type' => 'string'],
            'details' => ['type' => 'json'],
            'comment' => ['type' => 'string']
        ],
        'relations' => [
            'software' => ['type' => self::BELONGS_TO, 'model' => Software::class],
            'appliances' => ['type' => self::HAS_MANY, 'model' => Appliance::class, 'by' => '__software_item_id']
        ]
    ];

    /**
     * @return bool
     * @throws Exception
     */
    public function validate()
    {
        if (! ($this->software instanceof Software)) {
            throw new Exception('SoftwareItem: Неверный тип Software');
        }

        return true;
    }

    /**
     * @param Software $software
     * @param $version
     * @return SoftwareItem|bool
     */
    public static function findBySoftwareVersion(Software $software, $version)
    {
        return $software->softwareItems->filter(
            function ($softwareItem) use ($version) {
                return $version == $softwareItem->version;
            }
        )->first();
    }
}
