<?php

namespace App\Controllers;


use App\Models\Appliance;
use App\Models\ApplianceType;
use App\Models\City;
use App\Models\DataPort;
use App\Models\DPortType;
use App\Models\Module;
use App\Models\Office;
use App\Models\OfficeStatus;
use App\Models\Platform;
use App\Models\Region;
use App\Models\Software;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VPortType;
use App\Models\Vrf;
use T4\Core\Exception;
use T4\Core\MultiException;
use T4\Http\Request;
use T4\Mvc\Controller;

class Modal extends Controller
{
    use DebugTrait;

    public function actionAddRegion()
    {
        $this->data->path = (new Request())->path;
    }

    public function actionTestAddRegion()
    {

    }

    public function actionEditRegion($id)
    {
        if (false !== $region = Region::findByPK($id)) {
            $this->data->region = $region;
        } else {
            header('Location: /admin/regions');
        }
    }

    public function actionAddCity()
    {
        $this->data->regions = Region::findAll(['order' => 'title']);
    }

    public function actionEditCity($id)
    {
        if (true == $city = City::findByPK($id)) {
            $this->data->city = $city;
            $this->data->regions = Region::findAll(['order' => 'title']);
        } else {
            header('Location: /admin/cities');
        }
    }

    public function actionAddStatus()
    {

    }
    public function actionEditStatus($id)
    {
        if (true == $status = OfficeStatus::findByPK($id)) {
            $this->data->status = $status;
        } else {
            header('Location: /admin/cities');
        }
    }

    public function actionAddOffice()
    {
        $asc = function (City $city_1, City $city_2) {
            return strnatcmp($city_1->region->title, $city_2->region->title);
        };

        $this->data->regions = Region::findAll(['order' => 'title']);
        $this->data->cities = City::findAll()->uasort($asc);
        $this->data->statuses = OfficeStatus::findAll(['order' => 'title']);
    }

    public function actionEditOffice($id)
    {
        if (true == $office = Office::findByPK($id)) {
            $this->data->office = $office;

            $asc = function (City $city_1, City $city_2) {
                return strnatcmp($city_1->region->title, $city_2->region->title);
            };

            $this->data->regions = Region::findAll(['order' => 'title']);
            $this->data->cities = City::findAll()->uasort($asc);
            $this->data->statuses = OfficeStatus::findAll(['order' => 'title']);
        } else {
            header('Location: /admin/cities');
        }
    }

    public function actionAddApplianceType()
    {

    }

    public function actionEditApplianceType($id)
    {
        $this->data->applianceType = ApplianceType::findByPK($id);
    }

    public function actionAddPlatform()
    {
        $this->data->vendors = Vendor::findAll(['order' => 'title']);
    }

    public function actionEditPlatform($id)
    {
        $this->data->vendors = Vendor::findAll(['order' => 'title']);
        $this->data->platform = Platform::findByPK($id);
    }

    public function actionAddModule()
    {
        $this->data->vendors = Vendor::findAll(['order' => 'title']);
    }

    public function actionEditModule($id)
    {
        $this->data->vendors = Vendor::findAll(['order' => 'title']);
        $this->data->module = Module::findByPK($id);
    }

    public function actionAddSoftware()
    {
        $this->data->vendors = Vendor::findAll(['order' => 'title']);
    }

    public function actionEditSoftware($id)
    {
        $this->data->vendors = Vendor::findAll(['order' => 'title']);
        $this->data->software = Software::findByPK($id);
    }

    public function actionAddVendor()
    {

    }

    public function actionEditVendor($id)
    {
        $this->data->vendor = Vendor::findByPK($id);
    }

    public function actionOfficeDetail($id)
    {
        $this->data->office = Office::findByPK($id);
    }

    public function actionAddAppliance()
    {
        $this->data->offices = Office::findAll(['order' => 'title']);
        $this->data->vendors = Vendor::findAll(['order' => 'title']);
        $this->data->platforms = Platform::findAll(['order' => 'title']);
        $this->data->software = Software::findAll(['order' => 'title']);
        $this->data->modules = Module::findAll(['order' => 'title']);
        $this->data->applianceTypes = ApplianceType::findAll(['order' => 'type']);
    }

    public function actionEditAppliance($id)
    {
        $this->data->current = Appliance::findByPK($id);
        $this->data->offices = Office::findAll(['order' => 'title']);
        $this->data->vendors = Vendor::findAll(['order' => 'title']);
        $this->data->platforms = Platform::findAll(['order' => 'title']);
        $this->data->software = Software::findAll(['order' => 'title']);
        $this->data->modules = Module::findAll(['order' => 'title']);
        $this->data->applianceTypes = ApplianceType::findAll(['order' => 'type']);
        $this->data->portTypes = DPortType::findAll(['order' => 'type']);
        $this->data->vrfs = Vrf::findAll();
        $this->data->gvrf = Vrf::instanceGlobalVrf();
        if (0 < $this->data->current->dataPorts->count()) {
            $this->data->dataPorts = $this->data->current->dataPorts->uasort(
                function ($a, $b) {
                    return $a->details->portName <=> $b->details->portName;
                }
            );
        } else {
            $this->data->dataPorts = $this->data->current->dataPorts;
        }
    }

    public function actionAddPortType($type)
    {
        $this->data->type = $type;
        $this->data->path = (new Request())->path;
    }

    public function actionEditPortType($portType)
    {
        if ('voice' == $portType->type) {
            $this->data->portType = VPortType::findByPK($portType->id);
        } elseif ('data' == $portType->type) {
            $this->data->portType = DPortType::findByPK($portType->id);
        }
        $this->data->type = $portType->type;
    }

    public function actionAddDataPort($id)
    {
        $this->data->current = Appliance::findByPK($id);
        $this->data->portTypes = DPortType::findAll(['order' => 'type']);
        $this->data->vrfs = Vrf::findAll();
        $this->data->gvrf = Vrf::instanceGlobalVrf();
    }

    public function actionEditDataPort()
    {
        $this->data->currentAppliance = Appliance::findByPK($this->app->request->get->deviceId);
        $this->data->currentPort = DataPort::findByPK($this->app->request->get->portId);
        $this->data->portTypes = DPortType::findAll(['order' => 'type']);
        $this->data->vrfs = Vrf::findAll();
        $this->data->gvrf = Vrf::instanceGlobalVrf();
    }

    public function actionAddVrf()
    {

    }

    public function actionEditVrf($id)
    {
        $this->data->vrf = Vrf::findByPK($id);
    }

    public function actionAddNetwork()
    {
        $this->data->vrfs = Vrf::findAll();
        $this->data->gvrf = Vrf::instanceGlobalVrf();
    }
}