<?php

namespace App\Controllers;

use App\Components\Ip;
use App\Components\Timer;
use App\Models\Appliance;
use App\Models\DPortType;
use App\Models\ModuleItem;
use App\Models\Network;
use App\Models\Office;
use App\Models\Region;
use App\Models\Vendor;
use App\Models\Vrf;
use T4\Core\Exception;
use T4\Core\MultiException;
use T4\Mvc\Controller;

class Test extends Controller
{
    use DebugTrait;

    public function actionDefault()
    {

        $appliance = Appliance::findByPK(1407);
        $appliance->fill(
            [
                'lastUpdate'=> (new \DateTime('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s P')
//                'lastUpdate'=> (new \DateTime('now', new \DateTimeZone('Europe/Moscow')))->format('Y-m-d H:i:s P')
//                'lastUpdate'=> (new \DateTime())->format('Y-m-d H:i:s')
            ]
        )->save();
        var_dump(new \DateTime($appliance->lastUpdate));
        echo '--------------------';

        $appliance = Appliance::findByPK(1407);
        var_dump(((new \DateTime($appliance->lastUpdate))->setTimezone(new \DateTimeZone('Europe/Moscow')))->format('Y-m-d H:i'));
        var_dump((new \DateTime($appliance->lastUpdate))->setTimezone(new \DateTimeZone('Europe/Moscow')));
        var_dump((new \DateTime($appliance->lastUpdate))->setTimezone(new \DateTimeZone('America/Toronto')));
        var_dump((new \DateTime($appliance->lastUpdate))->setTimezone(new \DateTimeZone('America/Los_Angeles')));
        echo '--------------------';

        $lastUpdateDate = ((new \DateTime($appliance->lastUpdate))->setTimezone(new \DateTimeZone('Europe/Moscow')))->format('d.m.Y');
        $lastUpdateDateTime = 'last update: ' . ((new \DateTime($appliance->lastUpdate))->setTimezone(new \DateTimeZone('Europe/Moscow')))->format('d.m.Y H:i \M\S\K(P)');

        var_dump($lastUpdateDateTime);
        var_dump($lastUpdateDate);

        die;
    }

    public function actionRegions($region = null)
    {
        $timer = Timer::instance();
        if (!empty($region)) {
            $this->actionAddRegion($region);
        }
        $timer->fix('проверка empty');
        $this->data->regions = Region::findAll(['order' => 'title']);
        $timer->fix('Region::findAll');
    }

    public function actionTable()
    {
        $asc = function (Office $office_1, Office $office_2) {
            return (0 != $compareRes = strnatcmp(mb_strtolower($office_1->address->city->region->title), mb_strtolower($office_2->address->city->region->title))) ? $compareRes : 1;
        };

        $this->data->offices = Office::findAll()->uasort($asc);
        $this->data->activeLink->offices = true;

    }

    public function actionTime()
    {
//        $tz = new \DateTimeZone('Europe/Moscow');
//        var_dump($tz);die;
//        var_dump($res);die;
        $date = new \DateTime('2000-01-01', new \DateTimeZone('Europe/Moscow'));
        $date = new \DateTime('now', new \DateTimeZone('Europe/Moscow'));
        echo $date->format('Y-m-d H:i:sP') . "\n";

        $date->setTimezone(new \DateTimeZone('Pacific/Chatham'));
        echo $date->format('Y-m-d H:i:sP') . "\n";
        die;
    }


    public function actionGenNet()
    {
        $netArrayAddress = [
            '10.0.0.0/8',
            '10.10.0.0/16',
            '10.11.0.0/16',
            '10.12.0.0/16',
            '10.13.0.0/16',
            '10.14.0.0/16',
            '10.15.0.0/16',
            '10.10.0.0/24',
            '10.11.0.0/24',
            '10.12.0.0/24',
            '10.10.0.1/32',
            '10.11.0.1/32',
            '10.12.0.1/32',
            '11.0.0.0/8',
            '11.10.0.0/16',
            '11.11.0.0/16',
            '11.12.0.0/16',
            '11.13.0.0/16',
            '11.14.0.0/16',
            '11.15.0.0/16',
            '11.10.0.0/24',
            '11.11.0.0/24',
            '11.12.0.0/24',
            '11.10.0.1/32',
            '11.11.0.1/32',
            '11.12.0.1/32',
        ];
        $n = 50;
        while (count($netArrayAddress) > 0) {
            $key = array_rand($netArrayAddress, 1);
            $randAddress = $netArrayAddress[$key];
            unset($netArrayAddress[$key]);

            if (false !==Network::findByAddressVrf((new Ip($randAddress))->cidrNetwork, Vrf::instanceGlobalVrf())) {
                continue;
            }
            $net = (new Network())
                ->fill([
                    'address' => (new Ip($randAddress))->cidrNetwork,
                    'vrf' => Vrf::instanceGlobalVrf()
                ])
                ->save();

            echo $net->address . '<br>';
        }
        die;
    }

    public function actionDelAllNet()
    {
        Network::findAll()->delete();
        die;
    }

    public function actionNetSort()
    {
        $network3 = Network::findByAddressVrf('1.1.1.0/24', Vrf::instanceGlobalVrf());
        $res = $network3->findAllChildren(['address' => 'asc']);
        var_dump($res);die;
        Network::findAll()->delete();
        Vrf::findAll()->delete();
        $testVrf = (new Vrf(['name' => 'test', 'rd' => '10:10']))->save();
        $test2Vrf = (new Vrf(['name' => 'alfa', 'rd' => '20:10']))->save();
        $network1 = (new Network())
            ->fill([
                'address' => '1.1.2.0/25',
                'vrf' => Vrf::instanceGlobalVrf()
            ])
            ->save();
        $network1 = (new Network())
            ->fill([
                'address' => '1.1.2.0/25',
                'vrf' => $testVrf
            ])
            ->save();
        $network1 = (new Network())
            ->fill([
                'address' => '1.1.2.0/25',
                'vrf' => $test2Vrf
            ])
            ->save();
        $network2 = (new Network())
            ->fill([
                'address' => '1.1.1.0/24',
                'vrf' => Vrf::instanceGlobalVrf()
            ])
            ->save();
        $network3 = (new Network())
            ->fill([
                'address' => '1.1.3.0/24',
                'vrf' => Vrf::instanceGlobalVrf()
            ])
            ->save();
        $network3 = (new Network())
            ->fill([
                'address' => '1.1.4.0/25',
                'vrf' => Vrf::instanceGlobalVrf()
            ])
            ->save();
        $network3 = (new Network())
            ->fill([
                'address' => '1.1.2.0/24',
                'vrf' => Vrf::instanceGlobalVrf()
            ])
            ->save();
//        var_dump($network3->vrf->name);die;
        $result = Network::findAll(['vrf' => 'asc', 'address' => 'desc']);
//        var_dump($result);
        foreach ($result as $net) {
            echo $net->address . ' - ' . $net->vrf . '<br>';
        }
//        var_dump(Network::myFindAll(['address' => 'asc']));
        die;
    }

    public function actionSortVrf()
    {
        $result = Vrf::findAll(['name' => 'asc', 'rd' => 'asc']);
        foreach ($result as $vrf) {
            echo $vrf->name . ' - ' . $vrf->rd . '<br>';
        }
        die;
    }

    public function actionNetworkTree()
    {
        $this->data->roots = Network::findAllRoots();
    }

    public function actionTree()
    {

    }


    public function actionAddAppliance()
    {
        $this->data->response = 'Hello!';
        $json = '{"management_ip": "10.10.5.192", "chassis": "CISCO3945-CHASSIS", "modules": [{"serial": "FOC16352NNA", "product_number": "C3900-SPE250/K9"}, {"serial": "QCS1619P38Y", "product_number": "PWR-3900-AC"}, {"serial": "QCS1619P3BE", "product_number": "PWR-3900-AC"}, {"serial": "FOC163772DY", "product_number": "EHWIC-1GE-SFP-CU"}, {"serial": "FOC16382JCK", "product_number": "EHWIC-4ESG"}, {"serial": "FOC16382K39", "product_number": "EHWIC-4ESG"}, {"serial": "FOC16270WUG", "product_number": "SM-D-ES3G-48-P"}], "serial": "FCZ163377FU", "lotus_id": "101"}';
        $dec = json_decode($json);
        var_dump($dec);die;
    }

    public function actionAddRegion($region = null)
    {
        try {
            Region::getDbConnection()->beginTransaction();
            if (!empty(trim($region['many']))) {
                $pattern = '~[\n\r]~';
                $regsInString = preg_replace($pattern, '', trim($region['many']));
                $regInArray = explode(',', $regsInString);
                try {
                    foreach ($regInArray as $region) {
                        (new Region())
                            ->fill(['title' => trim($region)])
                            ->save();
                    }
                } catch (MultiException $e) {
                    $e->prepend(new Exception('Ошибка пакетного ввода'));
                    throw $e;
                }
            } else {
                (new Region())
                    ->fill(['title' => $region['one']])
                    ->save();
            }
            Region::getDbConnection()->commitTransaction();
        } catch (MultiException $e) {
            Region::getDbConnection()->rollbackTransaction();
            $this->data->errors = $e;
        }
    }

    public function actionOffices()
    {
        $asc = function (Office $office_1, Office $office_2) {
            return (0 != strnatcmp($office_1->address->city->region->title, $office_2->address->city->region->title)) ?: 1;
        };

        $this->data->offices = Office::findAll()->uasort($asc);
        $this->data->activeLink->offices = true;
    }
}