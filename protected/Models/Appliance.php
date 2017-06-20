<?php

namespace App\Models;

use T4\Core\Collection;
use T4\Core\Exception;
use T4\Orm\Model;

/**
 * Class Appliance
 * @package App\Models
 *
 * @property string $details
 * @property string $comment
 * @property string $lastUpdate
 *
 * @property ApplianceType $type
 * @property Office $location
 * @property Cluster $cluster
 * @property Vendor $vendor
 * @property Platform $platform
 * @property Software $software
 *
 * @property Collection|VoicePort[] $voicePorts
 * @property Collection|DataPort[] $dataPorts
 * @property Collection|ModuleItem[] $modules
 */
class Appliance extends Model
{
    protected static $schema = [
        'table' => 'equipment.appliances',
        'columns' => [
            'details' => ['type' => 'json'],
            'comment' => ['type' => 'text'],
            'lastUpdate' => ['type' => 'datetime']
        ],
        'relations' => [
            'type' => ['type' => self::BELONGS_TO, 'model' => ApplianceType::class, 'by' => '__type_id'],
            'location' => ['type' => self::BELONGS_TO, 'model' => Office::class, 'by' => '__location_id'],
            'cluster' => ['type' => self::BELONGS_TO, 'model' => Cluster::class],
            'vendor' => ['type' => self::BELONGS_TO, 'model' => Vendor::class],
            'platform' => ['type' => self::BELONGS_TO, 'model' => PlatformItem::class, 'by' => '__platform_item_id'],
            'software' => ['type' => self::BELONGS_TO, 'model' => SoftwareItem::class, 'by' => '__software_item_id'],
            'voicePorts' => ['type' => self::HAS_MANY, 'model' => VoicePort::class],
            'dataPorts' => ['type' => self::HAS_MANY, 'model' => DataPort::class],
            'modules' => ['type' => self::HAS_MANY, 'model' => ModuleItem::class]
        ]
    ];

    protected function validate()
    {
        if (! ($this->location instanceof Office)) {
            throw new Exception('Appliance: Неверный тип Office');
        }
        if (! ($this->vendor instanceof Vendor)) {
            throw new Exception('Appliance: Неверный тип Vendor');
        }
        if (! ($this->platform instanceof PlatformItem)) {
            throw new Exception('Appliance: Неверный тип Platform');
        }
        if (! ($this->software instanceof SoftwareItem)) {
            throw new Exception('Appliance: Неверный тип Software');
        }
        if (! ($this->type instanceof ApplianceType)) {
            throw new Exception('Appliance: Неверный тип ApplianceType');
        }
        if (!(is_null($this->cluster)) && !($this->cluster instanceof Cluster)) {
            throw new Exception('Appliance: Неверный тип Cluster');
        }

        $appliance = $this->platform->appliance;

        if (true === $this->isNew && ($appliance instanceof Appliance)) {
            throw new Exception('Такой Appliance уже существует');
        }
        if (true === $this->isUpdated && ($appliance instanceof Appliance) && ($appliance->getPk() != $this->getPk())) {
            throw new Exception('Такой Appliance уже существует');
        }

        return true;
    }

    protected function beforeSave()
    {
        return parent::beforeSave();
    }

    public function lastUpdateDate()
    {
        return $this->lastUpdate ? ((new \DateTime($this->lastUpdate))->setTimezone(new \DateTimeZone('Europe/Moscow')))->format('d.m.Y') : null;
    }

    public function lastUpdateDateTime()
    {
        return $this->lastUpdate ? ('last update: ' . ((new \DateTime($this->lastUpdate))->setTimezone(new \DateTimeZone('Europe/Moscow')))->format('d.m.Y H:i \M\S\K(P)')) : null;
    }

    /**
     * @return string managementIp|bool
     */
    public function getManagementIp()
    {
        $dataPort = $this->dataPorts->filter(
            function($dataPort) {
                return true === $dataPort->isManagement;
            }
        )->first();

        if ($dataPort instanceof DataPort) {
            return preg_replace('~/.+~', '', $dataPort->ipAddress);
        }

        return false;
    }
    public function getNoManagementPorts()
    {
        $result = $this->dataPorts->filter(
            function ($dPort) {
                return true !== $dPort->isManagement;
            }
        );
        return $result;
    }
    public function getManagementIpPort()
    {
        $dataPort = $this->dataPorts->filter(
            function($dataPort) {
                return true === $dataPort->isManagement;
            }
        )->first();

        if ($dataPort instanceof DataPort) {
            return $dataPort;
        }

        return false;
    }

    /**
     * @param string $vendorTitle
     * @param string $platformSerial
     * @return Appliance|bool
     */
    public static function findByVendorTitlePlatformSerial(string $vendorTitle, string $platformSerial)
    {
        return (self::findAll())->filter(
            function($appliance) use ($vendorTitle, $platformSerial) {
                return $vendorTitle == $appliance->vendor->title && $platformSerial == $appliance->platform->serialNumber;
            }
        )->first();
    }
}
