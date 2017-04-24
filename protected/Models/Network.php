<?php

namespace App\Models;

use App\Components\Ip;
use T4\Core\Collection;
use T4\Core\Exception;
use T4\Dbal\Query;
use T4\Orm\Model;

/**
 * Class Network
 * @package App\Models
 *
 * @property string $address
 * @property Collection|DataPort[] $hosts
 * @property Vlan $vlan
 * @property Vrf $vrf
 * @property Office $location
 */
class Network extends Model
{
    protected static $schema = [
        'table' => 'network.networks',
        'columns' => [
            'address' => ['type' => 'string'], //address in cidr notation i.e. 192.168.1.0/24
        ],
        'relations' => [
            'hosts' => ['type' => self::HAS_MANY, 'model' => DataPort::class, 'by' => '__network_id'],
            'vlan' => ['type' => self::BELONGS_TO, 'model' => Vlan::class, 'by' => '__vlan_id'],
            'vrf' => ['type' => self::BELONGS_TO, 'model' => Vrf::class, 'by' => '__vrf_id'],
            'location' => ['type' => self::BELONGS_TO, 'model' => Office::class, 'by' => '__location_id']
        ]
    ];

    protected static $extensions = ['tree'];

    protected function validateAddress($val)
    {
        if (!is_string($val)) {
            throw new Exception('Неверный тип свойства network->address');
        }
        $ip = new Ip($val);

        if (false === $ip->network || false === $ip->is_networkIp) {
            throw new Exception('Неверный адрес подсети');
        }
        return true;
    }

    protected function sanitizeAddress($val)
    {
        return (new Ip($val))->cidrAddress;
    }

    protected function validate()
    {
        if (! $this->vrf instanceof Vrf) {
            throw new Exception('VRF не найден');
        }
        if ((true === $this->isNew || true === $this->isUpdated) && false !== self::findByAddressVrf($this->address, $this->vrf)) {
            throw new Exception('Сеть с адресом ' . $this->address . '(VRF: ' . $this->vrf . ') уже существует');
        }
        return true;
    }

    protected function beforeSave()
    {
        if (true === $this->isNew) {
            $this->parent = $this->findParentNetwork();
        } elseif (true === $this->isUpdated) {
            foreach ($this->children as $child) {
                $child->parent = $this->parent;
                $child->save();
            }
            $this->parent = $this->findParentNetwork();
        }

        return parent::beforeSave();
    }

    protected function afterSave()
    {
        if (true === $this->wasUpdated || true === $this->wasNew) {
            if (false !== $this->parent) {
                foreach ($this->parent->children as $child) {
                    if (true === (new Ip($this->address))->is_parent(new Ip($child->address))) {
                        $child->parent = $this;
                        $child->save();
                    }
                }
            } else {
                foreach (self::__callStatic('findAllRoots', []) as $rootItem) {
                    if (true === (new Ip($this->address))->is_parent(new Ip($rootItem->address))) {
                        $rootItem->parent = $this;
                        $rootItem->save();
                    }
                }
            }
        }
        return parent::afterSave();
    }

    protected function beforeDelete()
    {
        if (false === $this->isNew) {
            foreach ($this->children as $child) {
                $child->parent = $this->parent;
                $child->save();
            }
        }
        return parent::beforeDelete();
    }

    public function findParentNetwork()
    {
        $query = 'WITH parents AS (SELECT DISTINCT * FROM network.networks WHERE address >> :subnet) SELECT * FROM parents WHERE address=(SELECT max(address) FROM parents)';
        return static::findByQuery($query, [':subnet' => $this->address]);
    }

    public static function findByAddressVrf($address, Vrf $vrf)
    {
        $result = Network::findAllByColumn('address', $address)->filter(function (Network $network) use ($vrf) {
            return ($network->vrf->getPk() == $vrf->getPk());
        });
        $result = $result->first();
        return ($result) ?: false;
    }
}