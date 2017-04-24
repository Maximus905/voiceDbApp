<?php

namespace App\Models;

use T4\Core\Exception;
use T4\Orm\Model;

/**
 * Class ModuleItem
 * @package App\Models
 *
 * @property string $serialNumber
 * @property string $inventoryNumber
 * @property string $details
 * @property string $comment
 *
 * @property Module $module
 * @property Appliance $appliance
 * @property Office $location
 */
class ModuleItem extends Model
{
    protected static $schema = [
        'table' => 'equipment.moduleItems',
        'columns' => [
            'serialNumber' => ['type' => 'string'],
            'inventoryNumber' => ['type' => 'string'],
            'details' => ['type' => 'json'],
            'comment' => ['type' => 'string']
        ],
        'relations' => [
            'module' => ['type' => self::BELONGS_TO, 'model' => Module::class],
            'appliance' => ['type' => self::BELONGS_TO, 'model' => Appliance::class],
            'location' => ['type' => self::BELONGS_TO, 'model' => Office::class]
        ]
    ];

    protected function validateSerialNumber($val)
    {
        return true;
    }

    protected function sanitizeSerialNumber($val)
    {
        return trim($val);
    }

    protected function validate()
    {
        if (false === $this->module) {
            throw new Exception('модуль не найден');
        }

        if (! ($this->appliance instanceof Appliance || false)) {
            throw new Exception('Неверный тип Appliance');
        }

        if (! ($this->appliance instanceof Appliance || $this->location instanceof Office)) {
            throw new Exception('Необходимо задать метоположение для офиса или модуля');
        }

        return true;
    }

    protected function beforeSave()
    {
        if ($this->appliance instanceof Appliance) {
            $this->location = $this->appliance->location;
        }
    }
}