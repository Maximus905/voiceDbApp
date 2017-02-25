<?php

namespace App\Models;

use T4\Core\Collection;
use T4\Orm\Model;

/**
 * Class City
 * @package App\Models
 *
 * @property string $title
 * @property string $diallingCode
 *
 * @property Region $region
 * @property Collection|Address[] $addresses
 */
class City extends Model
{
    protected static $schema = [
        'table' => 'geolocation.cities',
        'columns' => [
            'title' => ['type' => 'string'],
            'diallingCode' => ['type' => 'string']
        ],
        'relations' => [
            'region' => ['type' => self::BELONGS_TO, 'model' => Region::class],
            'addresses' => ['type' => self::HAS_MANY, 'model' => Address::class]
        ]
    ];

    protected function validateTitle($val)
    {
        return (!empty(trim($val)));
    }

    protected function validate()
    {
        if (empty($this->title)) {
            return false;
        }
        if (empty($this->region)) {
            return false;
        }
        return true;
    }
}