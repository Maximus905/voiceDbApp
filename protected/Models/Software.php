<?php

namespace App\Models;

use T4\Core\Collection;
use T4\Orm\Model;

/**
 * Class Software
 * @package App\Models
 *
 * @property string $title
 * @property Vendor $vendor
 * @property Collection|Appliance[] $appliances
 * @property Collection|SoftwareItem[] $softwareItems
 */
class Software extends Model
{
    protected static $schema = [
        'table' => 'equipment.software',
        'columns' => [
            'title' => ['type' => 'string']
        ],
        'relations' => [
            'vendor' => ['type' => self::BELONGS_TO, 'model' => Vendor::class],
            'appliances' => ['type' => self::HAS_MANY, 'model' => Appliance::class],
            'softwareItem' => ['type' => self::HAS_MANY, 'model' => SoftwareItem::class]
        ]
    ];
}