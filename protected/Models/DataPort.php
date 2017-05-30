<?php

namespace App\Models;

use App\Components\Ip;
use T4\Core\Collection;
use T4\Core\Exception;
use T4\Core\IArrayable;
use T4\Dbal\Query;
use T4\Orm\Model;

/**
 * Class DataPort
 * @package App\Models
 *
 * @property string $ipAddress
 * @property Network $network
 * @property string $macAddress
 * @property string $details
 * @property string $comment
 * @property bool $isManagement
 *
 * @property Appliance $appliance
 * @property DPortType $portType
 * @property Vrf $vrf
 */
class DataPort extends Model
{
    protected static $schema = [
        'table' => 'equipment.dataPorts',
        'columns' => [
            'ipAddress' => ['type' => 'string'],
            'macAddress' => ['type' => 'string'],
            'details' => ['type' => 'json'],
            'comment' => ['type' => 'text'],
            'isManagement' => ['type' => 'boolean']
        ],
        'relations' => [
            'appliance' => ['type' => self::BELONGS_TO, 'model' => Appliance::class],
            'portType' => ['type' => self::BELONGS_TO, 'model' => DPortType::class, 'by' => '__type_port_id'],
            'network' => ['type' => self::BELONGS_TO, 'model' => Network::class, 'by' => '__network_id']
        ]
    ];

    protected $vrf;

    protected function getIpAddress()
    {
        $key = 'ipAddress';
        $currentIpAddress = isset($this->__data[$key]) ? $this->__data[$key] : null;
        return (new Ip($currentIpAddress, 32))->cidrAddress;
    }

    protected function getVrf()
    {
        return empty($this->vrf) ? $this->vrf = $this->network->vrf : $this->vrf;
    }

    public function fill($data)
    {
        if ($data instanceof IArrayable) {
            $data = $data->toArray();
        } else {
            $data = (array)$data;
        }
        if (array_key_exists('vrf', $data) && $data['vrf'] instanceof Vrf) {
            $this->vrf = $data['vrf'];
            unset($data['vrf']);
        } else {
            throw new Exception('Неверно задан VRF');
        }
        return parent::fill($data);
    }


    /**
     * не может быть пустым
     * адрес должен быть валидным
     * должна быть явно задана маска
     * адрес не должен быть адресом сети
     *
     * @param $val
     * @return bool
     * @throws Exception
     *
     */
    protected function validateIpAddress($val)
    {
        $ip = new Ip($val);
        if (false === $ip->is_valid) {
            throw new Exception(implode('<br>', $ip->errors));
        }
        if (false === $ip->is_hostIp) {
            throw new Exception($ip->cidrAddress . ' не является адресом хоста' );
        }
        return true;
    }

    /**
     * наверное можно убрать санитацию IP
     *
     * @param Ip $val
     * @return mixed
     */
    protected function sanitizeIpAddress($val)
    {
        return (new Ip($val))->cidrAddress;
    }

    /**
     * не должен быть пустым
     * должен быть валидным
     *
     * @param $val
     * @return bool
     * @throws Exception
     */
    protected function validateMacAddress($val)
    {
        if (empty(trim($val))) {
            return false;
        }
        if (!empty(trim($val)) && false === filter_var(trim($val), FILTER_VALIDATE_MAC)) {
            throw new Exception('Неверный формат MAC адреса');
        }
        return true;
    }


    protected function sanitizeMacAddress($val)
    {
        return filter_var(trim($val), FILTER_VALIDATE_MAC);
    }

    protected function sanitizeComment($val)
    {
        return trim($val);
    }

    protected function sanitizeDetails($details)
    {
        if (is_array($details)) {
            foreach ($details as $key => $item) {
                $details[$key] = trim($item);
            }
        }
        return $details;
    }

    protected function validate()
    {
        $ip = new Ip($this->ipAddress);
        if (false === $this->appliance) {
            throw new Exception('Устройство не найдено');
        }
        if (false === $this->portType) {
            throw new Exception('Данный тип порта не найден');
        }

        //ищем записи с таким ip для новой записи
        if (true === $this->isNew && self::countAllByIpVrf($this->ipAddress, $this->vrf) > 0) {
            throw new Exception('IP адрес ' . $ip->address . ' уже используется.');
        }
        //валидация при изменении существующей записи
        if (true === $this->isUpdated) {
            $fromDb = self::findByIpVrf($this->ipAddress, $this->vrf);
            if (false !== $fromDb && $fromDb->getPk() != $this->getPk()) {
                throw new Exception('IP адрес ' . $ip->address . ' уже используется.');
            }
        }

        return true;
    }

    /**
     * назначаем подсеть для ip адреса данного порта и сохраняем ее.
     * если именяемый порт имел сетку с 32 маской - удаляем ее
     * @return bool
     * @throws Exception
     */
    protected function beforeSave()
    {
        $ip = (new Ip($this->ipAddress));
        if ($this->isNew) {
            if (false === $network = Network::findByAddressVrf($ip->cidrNetwork, $this->vrf)) {
                $network = (new Network())
                    ->fill([
                        'address' => ($ip->cidrNetwork),
                        'vrf' => $this->vrf
                    ])
                    ->save();
            } elseif ($network->children->count() > 0) {
                throw new Exception('Данная сеть разбита на подсети. Использование для хостовых IP невозможно.');
            }
            $this->network = $network;
        } elseif ($this->isUpdated) {
            if (false !== $network = Network::findByAddressVrf($ip->cidrNetwork, $this->vrf)) {
                //if net for new IP exists, it must not contain subnets.
                if ($network->children->count() > 0) {
                    throw new Exception('Данная сеть разбита на подсети. Использование для хостовых IP невозможно.');
                }
                $this->network = $network;
            } else {
                //network for new IP not found. Create it
                $network = (new Network())
                    ->fill([
                        'address' => ($ip->cidrNetwork),
                        'vrf' => $this->vrf
                    ]);
                //delete old DataPort
                DataPort::findByPK($this->getPk())->delete();
                //change current updated Data Port like new object
                $this->isUpdated = false;
                $this->isNew = true;
                //try save new network
                $network->save();
                $network->refresh();
                //new network must not contain any subnets
                if ($network->children->count() > 0) {
                    throw new Exception('Данная сеть разбита на подсети. Использование для хостовых IP невозможно.');
                }
                $this->network = $network;
            }
        }

        if (true === $this->isNew && null === $this->isManagement) {
            $this->isNotManagement();
        }

        return parent::beforeSave();
    }

    public function save()
    {
        if ($this->isUpdated) {
            $oldNetwork = (DataPort::findByPK($this->getPk()))->network;
            $saveResult = parent::save();
            if (false !== $saveResult && 32 == (new Ip($oldNetwork->address))->masklen) {
                $oldNetwork->delete();
            }
            return $saveResult;
        } else {
            return parent::save();
        }
    }

    protected function afterDelete()
    {
        if (32 == (new Ip($this->network->address))->masklen) {
            $this->network->delete();
        }
        return parent::afterDelete();
    }

    /**
     * @param $ip
     * @param Vrf $vrf
     * @return int
     */
    public static function countAllByIpVrf($ip, Vrf $vrf)
    {
        return self::findAllByIpVrf($ip, $vrf)->count();
    }

    /**
     * @param $ip
     * @param Vrf $vrf
     * @return bool|Collection|DataPort[]
     */
    public static function findAllByIpVrf($ip, Vrf $vrf)
    {
        $query = (new Query())
            ->select()
            ->from(DataPort::getTableName())
            ->where('host("ipAddress") = host(:ip)')
            ->params([':ip' => $ip]);

        /**
         * @var Collection|bool $result
         */
        $result = DataPort::findAllByQuery($query);
        $result = $result->filter(function ($dPort) use ($vrf) {
            /**
             * @var DataPort $dPort
             * @var Vrf $vrf
             */
            return ($dPort->network->vrf->rd == $vrf->rd);
        });
        return (null === $result) ? false : $result;
    }

    /**
     * @param $ip
     * @param Vrf $vrf
     * @return DataPort|bool
     */
    public static function findByIpVrf($ip, $vrf)
    {
        $result = self::findAllByIpVrf($ip, $vrf)->first();
        return (null === $result) ? false : $result;
    }


    public function isManagement()
    {
        $this->isManagement = true;
    }

    public function isNotManagement()
    {
        $this->isManagement = false;
    }
}
