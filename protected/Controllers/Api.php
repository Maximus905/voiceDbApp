<?php

namespace App\Controllers;

use App\ViewModels\ApiView_Devices;
use App\ViewModels\ApiView_Geo;
use App\ViewModels\Geo_View;
use T4\Core\Std;
use T4\Dbal\Query;
use T4\Mvc\Controller;

class Api extends Controller
{
    public function actionGetRegCenters()
    {
        // respond to preflights
        if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
            exit;
        }
        $filters = json_decode(file_get_contents('php://input'));
        
        $query = (new Query())
            ->select('regCenter')
            ->distinct()
            ->from(Geo_View::getTableName())
            ->where('"regCenter" NOTNULL')
            ->order('"regCenter"');
        $res = Geo_View::findAllByQuery($query);
        $output = [];
        /**
         * @var Geo_View $item
         */
        foreach ($res as $item) {
            $output[] = ['value' => $item->regCenter, 'label' => $item->regCenter];
        }
        $this->data->rc = $output;
    }
    
    public function actionGetRegions()
    {
        // respond to preflights
        if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
            exit;
        }
        $filters = new Std(json_decode(file_get_contents('php://input')));
        $condition = ['region_id NOTNULL'];
        if (!empty($filters->value)) {
            $condition[] = $filters->accessor . $filters->statement . $filters->value;
        }
        $query = (new Query())
            ->select(['region_id', 'region'])
            ->from(ApiView_Geo::getTableName())
            ->where(join(' AND ', $condition))
            ->group('region_id, region')
            ->order('region');
        $res = ApiView_Geo::findAllByQuery($query);
        $output = [];
        /**
         * @var ApiView_Geo $item
         */
        foreach ($res as $item) {
            $output[] = ['value' => $item->region_id, 'label' => $item->region];
        }
        $this->data->rc = $output;
    }
    
    public function actionGetCities()
    {
        // respond to preflights
        if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
            exit;
        }
        $filters = new Std(json_decode(file_get_contents('php://input')));
        $condition = ['city_id NOTNULL'];
        if (!empty($filters->value)) {
            $condition[] = $filters->accessor . $filters->statement . $filters->value;
        }
        $query = (new Query())
            ->select(['city_id', 'city'])
            ->from(ApiView_Geo::getTableName())
            ->where(join(' AND ', $condition))
            ->group('city_id, city')
            ->order('city');
        $res = ApiView_Geo::findAllByQuery($query);
        $output = [];
        /**
         * @var ApiView_Geo $item
         */
        foreach ($res as $item) {
            $output[] = ['value' => $item->city_id, 'label' => $item->city];
        }
        $this->data->rc = $output;
    }
    
    public function actionGetOffices()
    {
        // respond to preflights
        if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
            exit;
        }
        $filters = new Std(json_decode(file_get_contents('php://input')));
        $condition = ['location_id NOTNULL'];
        if (!empty($filters->value)) {
            $condition[] = $filters->accessor . $filters->statement . $filters->value;
        }
        $query = (new Query())
            ->select(['location_id', 'office'])
            ->from(ApiView_Geo::getTableName())
            ->where(join(' AND ', $condition))
            ->group('location_id, office')
            ->order('"office"');
        $res = ApiView_Geo::findAllByQuery($query);
        $output = [];
        /**
         * @var ApiView_Geo $item
         */
        foreach ($res as $item) {
            $output[] = ['value' => $item->location_id, 'label' => $item->office];
        }
        $this->data->rc = $output;
    }
    
    public function actionGetDevTypes()
    {
        // respond to preflights
        if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
            exit;
        }
        $filters = new Std(json_decode(file_get_contents('php://input')));
        $condition = ['dev_type_id NOTNULL'];
        if (!empty($filters->value)) {
            $condition[] = $filters->accessor . $filters->statement . $filters->value;
        }
        $query = (new Query())
            ->select(['dev_type_id', 'dev_type'])
            ->from(ApiView_Devices::getTableName())
            ->where(join(' AND ', $condition))
            ->group('dev_type_id, dev_type')
            ->order('"dev_type"');
        $res = ApiView_Devices::findAllByQuery($query);
        $output = [];
        /**
         * @var ApiView_Devices $item
         */
        foreach ($res as $item) {
            $output[] = ['value' => $item->dev_type_id, 'label' => $item->dev_type];
        }
        $this->data->rc = $output;
    }
    
    public function actionGetPlatforms()
    {
        // respond to preflights
        if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
            exit;
        }
        $filters = new Std(json_decode(file_get_contents('php://input')));
        $condition = ['platform_id NOTNULL'];
        if (!empty($filters->value)) {
            $condition[] = $filters->accessor . $filters->statement . $filters->value;
        }
        $query = (new Query())
            ->select(['platform_id', 'platform'])
            ->from(ApiView_Devices::getTableName())
            ->where(join(' AND ', $condition))
            ->group('platform_id, platform')
            ->order('"platform"');
        $res = ApiView_Devices::findAllByQuery($query);
        $output = [];
        /**
         * @var ApiView_Devices $item
         */
        foreach ($res as $item) {
            $output[] = ['value' => $item->platform_id, 'label' => $item->platform];
        }
        $this->data->rc = $output;
    }
    
    public function actionGetSoftwareList()
    {
        // respond to preflights
        if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
            exit;
        }
        $filters = new Std(json_decode(file_get_contents('php://input')));
        $condition = ['software_id NOTNULL'];
        if (!empty($filters->value)) {
            $condition[] = $filters->accessor . $filters->statement . $filters->value;
        }
        $query = (new Query())
            ->select(['software_id', 'software'])
            ->from(ApiView_Devices::getTableName())
            ->where(join(' AND ', $condition))
            ->group('software_id, software')
            ->order('"software"');
        $res = ApiView_Devices::findAllByQuery($query);
        $output = [];
        /**
         * @var ApiView_Devices $item
         */
        foreach ($res as $item) {
            $output[] = ['value' => $item->software_id, 'label' => $item->software];
        }
        $this->data->rc = $output;
    }
    
    public function actionGetDevData($id)
    {
        // respond to preflights
        if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
            exit;
        }
        $fields = [
            'dev_id', 'location_id', 'platform_id', 'platform_item_id', 'software_id', 'software_item_id', 'vendor_id', 'dev_type_id',
            'dev_comment', 'software_comment', 'dev_last_update', 'dev_in_use', 'platform_sn', 'platform_sn_alt', 'is_hw', 'software_ver',
            'dev_details', 'software_details'
        ];
        $condition = 'dev_id = :dev_id';
        $query = (new Query())
            ->select($fields)
            ->from(ApiView_Devices::getTableName())
            ->where($condition)
            ->group(join(',',$fields))
            ->params([':dev_id' => $id]);
        $res = ApiView_Devices::findAllByQuery($query);
        $this->data->rc = $res->toArrayRecursive();
    }
}