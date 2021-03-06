<?php

namespace App\Models;

use T4\Core\Collection;
use T4\Orm\Model;

/**
 * Class Contract
 * @package App\Models
 *
 * @property string $number
 * @property string $date
 * @property string $pathToScan
 *
 * @property ContractType $contractType
 * @property PartnerOffice $partnerOffice
 * @property Collection|Person[] $persons
 * @property Collection|PstnNumber[] $pstnNumbers
 */
class Contract extends Model
{
    protected static $schema = [
        'table' => 'partners.contracts',
        'columns' => [
            'number' => ['type' => 'string'],
            'date' => ['type' => 'string'],
            'pathToScan' => ['type' => 'string']
        ],
        'relations' => [
            'contractType' => ['type' => self::BELONGS_TO, 'model' => ContractType::class, 'by' => '__contract_type_id'],
            'partnerOffice' => ['type' => self::BELONGS_TO, 'model' => PartnerOffice::class, 'by' => '__partner_office_id'],
            'persons' => [
                'type' => self::MANY_TO_MANY,
                'model' => Person::class,
                'pivot' => 'partners.contracts_to_persons'
            ],
            'pstnNumbers' => [
                'type' => self::MANY_TO_MANY,
                'model' => PstnNumber::class,
                'pivot' => 'telephony.pstnNumbers_to_contracts',
                'that' => '__pstn_number_id'
            ]
        ]
    ];
}