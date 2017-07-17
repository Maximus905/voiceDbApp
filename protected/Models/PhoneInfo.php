<?php
namespace App\Models;

use T4\Core\Exception;
use T4\Orm\Model;

class PhoneInfo extends Model
{
    protected static $schema = [
        'table' => 'equipment.phoneInfo',
        'columns' => [
            'type' => ['type' => 'string'],
            'name' => ['type' => 'string'],
            'macAddress' => ['type' => 'string'],
            'prefix' => ['type' => 'int'],
            'phoneDN' => ['type' => 'int'],
            'status' => ['type' => 'string'],
            'description' => ['type' => 'string'],
            'css' => ['type' => 'string'],
            'devicePool' => ['type' => 'string'],
            'alertingName' => ['type' => 'string'],
            'partition' => ['type' => 'string'],
        ],
        'relations' => [
            'phone' => ['type' => self::BELONGS_TO, 'model' => Appliance::class],
        ],
    ];


    protected function validate()
    {
        if (!($this->phone instanceof Appliance)) {
            throw new Exception('PhoneInfo: Неверный тип Appliance');
        }

        $phoneInfo = PhoneInfo::findByColumn('name', $this->name);

        if (true === $this->isNew && ($phoneInfo instanceof PhoneInfo)) {
            throw new Exception('Такой PhoneInfo уже существует');
        }

        if (true === $this->isUpdated && ($phoneInfo instanceof PhoneInfo) && ($phoneInfo->getPk() != $this->getPk())) {
            throw new Exception('Такой PlatformItem уже существует');
        }

        return true;
    }
}
