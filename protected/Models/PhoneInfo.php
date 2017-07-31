<?php
namespace App\Models;

use T4\Core\Exception;
use T4\Orm\Model;

class PhoneInfo extends Model
{
    protected static $schema = [
        'table' => 'equipment.phoneInfo',
        'columns' => [
            'model' => ['type' => 'string'],
            'name' => ['type' => 'string'],
            'prefix' => ['type' => 'int'],
            'phoneDN' => ['type' => 'int'],
            'status' => ['type' => 'string'],
            'description' => ['type' => 'string'],
            'css' => ['type' => 'string'],
            'devicePool' => ['type' => 'string'],
            'alertingName' => ['type' => 'string'],
            'partition' => ['type' => 'string'],
            'timezone' => ['type' => 'string'], //
            'dhcpEnabled' => ['type' => 'boolean'],
            'dhcpServer' => ['type' => 'string'],
            'domainName' => ['type' => 'string'],
            'tftpServer1' => ['type' => 'string'],
            'tftpServer2' => ['type' => 'string'],
            'defaultRouter' => ['type' => 'string'],
            'dnsServer1' => ['type' => 'string'],
            'dnsServer2' => ['type' => 'string'],
            'callManager1' => ['type' => 'string'],
            'callManager2' => ['type' => 'string'],
            'callManager3' => ['type' => 'string'],
            'callManager4' => ['type' => 'string'],
            'vlanId' => ['type' => 'string'],
            'userLocale' => ['type' => 'string'],
            'cdpNeighborDeviceId' => ['type' => 'string'],
            'cdpNeighborIP' => ['type' => 'string'],
            'cdpNeighborPort' => ['type' => 'string'],
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
