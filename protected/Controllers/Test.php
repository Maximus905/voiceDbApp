<?php

namespace App\Controllers;

use App\Models\Appliance;
use App\Models\City;
use App\Models\ModuleItem;
use App\Models\Network;
use App\Models\Office;
use App\Models\Region;
use App\Models\Vendor;
use App\Models\Vrf;
use T4\Core\Collection;
use T4\Core\Exception;
use T4\Core\MultiException;
use T4\Core\Std;
use T4\Mvc\Controller;
use T4\Orm\Model;

class Test extends Controller
{
    public function actionDefault()
    {
        $url = "http://voice.loc/rServer/testServer.json";
//        $url = "http://10.99.120.170/rServer/serverTest.json";
//        $url = "http://voice.loc/rServer/serverTest";

        $files= [1,2,3,4,5,6];
        $result = [];

        foreach ($files as $file) {
            $curl = curl_init($url);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            $result[] =  curl_exec($curl);
            if (curl_error($curl)) {
                echo curl_error($curl);die;
            }
            curl_close($curl);
        }
        var_dump($result);
        die;
    }

    public function actionTestModule()
    {
        $module = (new \App\Models\Module())
            ->fill([
                'title' => 'test',
                'vendor' => Vendor::findByTitle('test')
            ])
            ->save();
        $item1 = (new ModuleItem())
            ->fill([
                'serialNumber' => 'sn1',
                'appliance' => Appliance::findAll()->first(),
                'location' => Office::findAll()->first(),
                'module' => $module
            ])
            ->save();
        var_dump($module->moduleItems);
        $item1->delete();
        $module->delete();
        die;

    }

    public function actionNetworks()
    {
        $this->data->roots = Network::findAllRoots();
    }

    public function actionNetworkTree()
    {
        $this->data->roots = Network::findAllRoots();
    }

    public function actionTree()
    {

    }


    public function actionRegions($region = null)
    {
        //var_dump($region);
        if (!empty($region)) {
            $this->actionAddRegion($region);
        }
        $this->data->regions = Region::findAll(['order' => 'title']);
        //var_dump($this->data);
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