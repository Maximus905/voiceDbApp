<?php
namespace App\Components;

use App\Models\Appliance;
use App\Models\Office;
use App\Models\PhoneInfo;
use T4\Core\Exception;
use T4\Core\MultiException;
use T4\Core\Std;

class DSPphone extends Std
{
    const PHONE = 'phone';
    const PHONESOFT = 'Phone Soft';
    const VENDOR = 'CISCO'; // Todo - пока так

    protected $dataSet;
    protected $debugLogger;


    /**
     * DSPphone constructor.
     * @param null $dataSet
     */
    public function __construct($dataSet)
    {
        $this->dataSet = $dataSet;
        $this->debugLogger = RLogger::getInstance('DSPphone', realpath(ROOT_PATH . '/Logs/debug.log'));
    }


    /**
     * @return bool
     * @throws Exception
     */
    public function run()
    {
        $this->verifyDataSet();

        $this->debugLogger->info('START: ' . '[name]=' . $this->dataSet->ipAddress);

        // Find the office by the cucm's IP
        $location = (Appliance::findByManagementIP($this->dataSet->cmIpAddress))->location;
        if (!($location instanceof Office)) {
            throw new Exception('Location not found. CucmIP = ' . $this->dataSet->cmIpAddress);
        }

        $this->debugLogger->info('process: ' . '[name]=' . $this->dataSet->ipAddress . '; [office]=' . $location->title);

        // Create a DataSet for a phone(appliance)
        $softwareVersion = (1 == preg_match('~6921~', $this->dataSet->type)) ? $this->dataSet->appLoadID : $this->dataSet->versionID;
        $phoneDataSet = (new Std())->fill([
            'applianceType' => self::PHONE,
            'platformVendor' => self::VENDOR,
            'platformTitle' => ($this->dataSet->modelNumber) ?? $this->dataSet->type,
            'platformSerial' => ($this->dataSet->serialNumber) ?? $this->dataSet->name,
            'applianceSoft' => self::PHONESOFT,
            'softwareVersion' => $softwareVersion,
            'ip' => $this->dataSet->ipAddress,
            'LotusId' => $location->lotusId,
            'hostname' => $this->dataSet->cmName,
            'chassis' => ($this->dataSet->modelNumber) ?? $this->dataSet->type,
            'applianceModules' => [],
        ]);

        // Find the PhoneInfo by the name and find the Phone by PhoneInfo. Update the Phone
        if (($phoneInfo = PhoneInfo::findByColumn('name', $this->dataSet->name)) instanceof PhoneInfo) {
            $phone = (new DSPappliance($phoneDataSet, $phoneInfo->phone))->returnAppliance();
        } else {
            $phone = (new DSPappliance($phoneDataSet))->returnAppliance();
            $phoneInfo = new PhoneInfo();
        }

        $this->debugLogger->info('process: ' . '[name]=' . $this->dataSet->ipAddress . '; [phone]= OK');


        // Update PhoneInfo
        $phoneInfo->fill([
            'phone' => $phone,
            'type' => $this->dataSet->type,
            'name' => $this->dataSet->name,
            'macAddress' => ($this->dataSet->macAddress) ?? substr($this->dataSet->name,-12), //---
            'prefix' => preg_replace('~\..+~','',$this->dataSet->prefix),
            'phoneDN' => $this->dataSet->phoneDN,
            'status' => $this->dataSet->status,
            'description' => $this->dataSet->description,
            'css' => $this->dataSet->css,
            'devicePool' => $this->dataSet->devicePool,
            'alertingName' => $this->dataSet->alertingName,
            'partition' => $this->dataSet->partition,
        ])->save();

        $this->debugLogger->info('process: ' . '[name]=' . $this->dataSet->ipAddress . '; [phoneInfo]= OK');
        $this->debugLogger->info('END: ' . '[name]=' . $this->dataSet->ipAddress);

        return true;
    }

    protected function verifyDataSet()
    {
        if (empty($this->dataSet)) {
            throw new Exception('DATASET: Empty an input datasets');
        }

        $errors = new MultiException();

        if (!isset($this->dataSet->cmName)) {
            $errors->add(new Exception('DATASET: No field cmName'));
        }
        if (!isset($this->dataSet->cmIpAddress)) {
            $errors->add(new Exception('DATASET: No field cmIpAddress'));
        }
        if (empty($this->dataSet->name)) {
            $errors->add(new Exception('DATASET: Empty or No field name'));
        }
        if (!isset($this->dataSet->ipAddress)) {
            $errors->add(new Exception('DATASET: No field ipAddress'));
        }
        if (!isset($this->dataSet->description)) {
            $errors->add(new Exception('DATASET: No field description'));
        }
        if (!isset($this->dataSet->css)) {
            $errors->add(new Exception('DATASET: No field css'));
        }
        if (!isset($this->dataSet->devicePool)) {
            $errors->add(new Exception('DATASET: No field devicePool'));
        }
        if (!isset($this->dataSet->prefix)) {
            $errors->add(new Exception('DATASET: No field prefix'));
        }
        if (!isset($this->dataSet->phoneDN)) {
            $errors->add(new Exception('DATASET: No field phoneDN'));
        }
        if (!isset($this->dataSet->alertingName)) {
            $errors->add(new Exception('DATASET: No field alertingName'));
        }
        if (!isset($this->dataSet->partition)) {
            $errors->add(new Exception('DATASET: No field partition'));
        }
        if (!isset($this->dataSet->type)) {
            $errors->add(new Exception('DATASET: No field type'));
        }

        // Если DataSet не валидный, то заканчиваем работу
        if (0 < $errors->count()) {
            throw $errors;
        }
    }
}
