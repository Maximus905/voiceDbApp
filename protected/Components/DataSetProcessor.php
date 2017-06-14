<?php
namespace App\Components;

use App\Models\Appliance;
use App\Models\ApplianceType;
use App\Models\Cluster;
use App\Models\DataPort;
use App\Models\DPortType;
use App\Models\Module;
use App\Models\ModuleItem;
use App\Models\Office;
use App\Models\Platform;
use App\Models\PlatformItem;
use App\Models\Software;
use App\Models\SoftwareItem;
use App\Models\Vendor;
use App\Models\Vrf;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use T4\Core\Collection;
use T4\Core\Exception;
use T4\Core\MultiException;
use T4\Core\Std;

class DataSetProcessor extends Std
{
    const DST_APPLIANCE = 'appliance';
    const DST_CLUSTER = 'cluster';
    const DST_ERROR = 'error';
    const SLEEPTIME = 100000; // микросекунды
    const ITERATIONS = 520; // Колличество попыток получить доступ к db.lock файлу
    const DBLOCKFILE = ROOT_PATH_PROTECTED . '/db.lock';

    protected $dataSet;
    protected $dbLockFile;
    protected $firstClusterAppliance = true;

    /**
     * DataSetProcessor constructor.
     * @param $dataSet
     */
    public function __construct($dataSet)
    {
        $this->dataSet = $dataSet;
    }

    /**
     *
     */
    public function run()
    {
        $this->verifyDataSet();

        if (self::DST_APPLIANCE == $this->dataSet->dataSetType) {
            return $this->processApplianceDataSet();
        }
        if (self::DST_CLUSTER == $this->dataSet->dataSetType) {
            return $this->processClusterDataSet();
        }
        if (self::DST_ERROR == $this->dataSet->dataSetType) {
            return $this->processErrorDataSet();
        }

        return false;
    }

    /**
     *
     */
    protected function processClusterDataSet()
    {
        $cluster = $this->processClusterItemDataSet($this->dataSet->hostname);

        foreach ($this->dataSet->clusterAppliances as $dataSetClusterAppliance) {
            $this->processClusterApplianceDataSet($cluster, $dataSetClusterAppliance);
        }
    }

    /**
     * @param string $title
     * @return Cluster|bool
     */
    protected function processClusterItemDataSet(string $title)
    {
        $cluster = Cluster::findByTitle($title);

        if (!($cluster instanceof Cluster)) {
            $cluster = (new Cluster())
                ->fill([
                    'title' => $title
                ])
                ->save();
        }

        return $cluster;
    }

    /**
     * @param Cluster $cluster
     * @param $dataSetClusterAppliance
     * @throws Exception
     */
    protected function processClusterApplianceDataSet(Cluster $cluster, $dataSetClusterAppliance)
    {
        $dataSetClusterAppliance = $this->fixClusterApplianceDataSet($dataSetClusterAppliance);

        if (false === $this->dbLock()) {
            throw new Exception('Can not get the lock file');
        }

        try {
            Appliance::getDbConnection()->beginTransaction();

            $office = $this->processLocationDataSet($dataSetClusterAppliance->LotusId);
            $vendor = $this->processVendorDataSet($dataSetClusterAppliance->platformVendor);
            $platform = $this->processPlatformDataSet($vendor ,$dataSetClusterAppliance->chassis);
            $platformItem = $this->processPlatformItemDataSet($platform ,$dataSetClusterAppliance->platformSerial);
            $software = $this->processSoftwareDataSet($vendor ,$dataSetClusterAppliance->applianceSoft);
            $softwareItem = $this->processSoftwareItemDataSet($software ,$dataSetClusterAppliance->softwareVersion);
            $applianceType = $this->processApplianceTypeDataSet($dataSetClusterAppliance->applianceType);
            $appliance = $this->processApplianceItemDataSet($office, $applianceType, $vendor, $platformItem, $softwareItem,$dataSetClusterAppliance->hostname, $cluster);
            $this->processModulesDataSet($vendor, $appliance, $office,$dataSetClusterAppliance->applianceModules);

            // IP address прописываем только у первого Appliance, входящего в состав кластера
            if (true === $this->firstClusterAppliance) {
                $this->processDataPortDataSet($appliance, $dataSetClusterAppliance->ip);
                $this->firstClusterAppliance = false;
            }

            Appliance::getDbConnection()->commitTransaction();

        } catch (Exception $e) {
            Appliance::getDbConnection()->rollbackTransaction();
            throw new Exception($e->getMessage());
        }

        $this->dbUnLock();
    }

    /**
     * @param $dataSet
     * @return mixed
     */
    protected function fixClusterApplianceDataSet($dataSet)
    {
        $matches = [
            $dataSet->platformVendor,
            '-CHASSIS',
            'CHASSIS',
        ];
        foreach ($matches as $match) {
            $dataSet->chassis = mb_ereg_replace($match, '', $dataSet->chassis, "i");
        }

        return $dataSet;
    }

    /**
     * @throws Exception
     */
    protected function processApplianceDataSet()
    {
        $this->beforeProcessApplianceDataSet();

        if (false === $this->dbLock()) {
            throw new Exception('Can not get the lock file');
        }

        try {
            Appliance::getDbConnection()->beginTransaction();

            $office = $this->processLocationDataSet($this->dataSet->LotusId);
            $vendor = $this->processVendorDataSet($this->dataSet->platformVendor);
            $platform = $this->processPlatformDataSet($vendor ,$this->dataSet->chassis);
            $platformItem = $this->processPlatformItemDataSet($platform ,$this->dataSet->platformSerial);
            $software = $this->processSoftwareDataSet($vendor ,$this->dataSet->applianceSoft);
            $softwareItem = $this->processSoftwareItemDataSet($software ,$this->dataSet->softwareVersion);
            $applianceType = $this->processApplianceTypeDataSet($this->dataSet->applianceType);
            $appliance = $this->processApplianceItemDataSet($office, $applianceType, $vendor, $platformItem, $softwareItem,$this->dataSet->hostname);
            $this->processModulesDataSet($vendor, $appliance, $office,$this->dataSet->applianceModules);
            $this->processDataPortDataSet($appliance, $this->dataSet->ip);

            Appliance::getDbConnection()->commitTransaction();

        } catch (Exception $e) {
            Appliance::getDbConnection()->rollbackTransaction();
            throw new Exception($e->getMessage());
        }

        $this->dbUnLock();

        return true;
    }

    /**
     *
     */
    protected function beforeProcessApplianceDataSet()
    {
        $matches = [
            $this->dataSet->platformVendor,
            '-CHASSIS',
            'CHASSIS',
        ];
        foreach ($matches as $match) {
            $this->dataSet->chassis = mb_ereg_replace($match, '', $this->dataSet->chassis, "i");
        }
    }

    /**
     * @param $lotusId
     * @return Office
     * @throws Exception
     */
    protected function processLocationDataSet($lotusId)
    {
        $office = Office::findByLotusId($lotusId);

        if (!($office instanceof Office)) {
            throw new Exception('Location not found, LotusId = ' . $lotusId);
        }

        return $office;
    }

    /**
     * @param $title
     * @return Vendor|bool
     */
    protected function processVendorDataSet($title)
    {
        $vendor = Vendor::findByTitle($title);

        if (!($vendor instanceof Vendor)) {
            $vendor = (new Vendor())
                ->fill([
                    'title' => $title
                ])
                ->save();
        }

        return $vendor;
    }

    /**
     * @param Vendor $vendor
     * @param $title
     * @return Platform|bool
     */
    protected function processPlatformDataSet(Vendor $vendor, $title)
    {
        $platform = Platform::findByVendorTitle($vendor, $title);

        if (!($platform instanceof Platform)) {
            $platform = (new Platform())
                ->fill([
                    'vendor' => $vendor,
                    'title' => $title
                ])
                ->save();
        }

        return $platform;
    }

    /**
     * @param Platform $platform
     * @param $serialNumber
     * @return PlatformItem|bool
     */
    protected function processPlatformItemDataSet(Platform $platform, $serialNumber)
    {
        $platformItem = PlatformItem::findByPlatformSerial($platform, $serialNumber);

        if (!($platformItem instanceof PlatformItem)) {
            $platformItem = (new PlatformItem())
                ->fill([
                    'platform' => $platform,
                    'serialNumber' => $serialNumber
                ])
                ->save();
        }

        return $platformItem;
    }

    /**
     * @param Vendor $vendor
     * @param $title
     * @return Software|bool
     */
    protected function processSoftwareDataSet(Vendor $vendor, $title)
    {
        $software = Software::findByVendorTitle($vendor, $title);

        if (!($software instanceof Software)) {
            $software = (new Software())
                ->fill([
                    'vendor' => $vendor,
                    'title' => $title
                ])
                ->save();
        }

        return $software;
    }

    /**
     * @param Software $software
     * @param $version
     * @return SoftwareItem|bool
     */
    protected function processSoftwareItemDataSet(Software $software, $version)
    {
        $softwareItem = (new SoftwareItem())
            ->fill([
                'software' => $software,
                'version' => $version
            ])
            ->save();

        return $softwareItem;
    }

    /**
     * @param $type
     * @return ApplianceType|bool
     */
    protected function processApplianceTypeDataSet($type)
    {
        $applianceType = ApplianceType::findByType($type);

        if (!($applianceType instanceof ApplianceType)) {
            $applianceType = (new ApplianceType())
                ->fill([
                    'type' => $type
                ])
                ->save();
        }

        return $applianceType;
    }

    /**
     * @param Office $office
     * @param ApplianceType $applianceType
     * @param Vendor $vendor
     * @param PlatformItem $platformItem
     * @param SoftwareItem $softwareItem
     * @param $hostname
     * @param Cluster|null $cluster
     * @return Appliance
     */
    protected function processApplianceItemDataSet(
        Office $office,
        ApplianceType $applianceType,
        Vendor $vendor,
        PlatformItem $platformItem,
        SoftwareItem $softwareItem,
        $hostname,
        Cluster $cluster = null
    )
    {
        $appliance = ($platformItem->appliance instanceof Appliance) ? $platformItem->appliance : (new Appliance());
        $appliance->fill([
            'cluster' => $cluster,
            'location' => $office,
            'type' => $applianceType,
            'vendor' => $vendor,
            'platform' => $platformItem,
            'software' => $softwareItem,
            'lastUpdate'=> (new \DateTime('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s P'),
            'details' => [
                'hostname' => $hostname,
            ],
        ])->save();

        return $appliance;
    }

    /**
     * @param Vendor $vendor
     * @param Appliance $appliance
     * @param Office $office
     * @param $modulesDataSet
     */
    protected function processModulesDataSet(Vendor $vendor, Appliance $appliance, Office $office, $modulesDataSet)
    {
        $usedModules = $this->processUsedModulesDataSet($vendor, $appliance, $office, $modulesDataSet);
        $this->processUnUsedModulesDataSet($appliance, $usedModules);
    }

    /**
     * @param Vendor $vendor
     * @param Appliance $appliance
     * @param Office $office
     * @param $modulesDataSet
     * @return Collection
     */
    protected function processUsedModulesDataSet(Vendor $vendor, Appliance $appliance, Office $office, $modulesDataSet)
    {
        $usedModules = new Collection();

        foreach ($modulesDataSet as $moduleDataSet) {
            $module = $this->processModuleDataSet($vendor, $moduleDataSet->product_number, $moduleDataSet->description);
            $moduleItem = $this->processModuleItemDataSet($appliance, $office, $module, $moduleDataSet->serial);
            $usedModules->add($moduleItem);
        }

        return $usedModules;
    }

    /**
     * @param Vendor $vendor
     * @param $title
     * @param $description
     * @return Module|bool
     */
    protected function processModuleDataSet(Vendor $vendor, $title, $description)
    {
        $vendor->refresh();
        $module = Module::findByVendorTitle($vendor, $title);
        if (!($module instanceof Module)) {
            $module = (new Module())
                ->fill([
                    'vendor' => $vendor,
                    'title' => $title,
                    'description' => $description,
                ])
                ->save();
            $vendor->refresh();
        }

        return $module;
    }

    /**
     * @param Appliance $appliance
     * @param Office $office
     * @param Module $module
     * @param $serialNumber
     * @return ModuleItem
     */
    protected function processModuleItemDataSet(Appliance $appliance, Office $office, Module $module, $serialNumber)
    {
        $module->refresh();
        $moduleItem = ModuleItem::findByVendorSerial($module->vendor->title, $serialNumber);

        $moduleItem = ($moduleItem instanceof ModuleItem) ? $moduleItem : (new ModuleItem());
        $moduleItem->found();
        $moduleItem->fill([
            'module' => $module,
            'serialNumber' => $serialNumber,
            'appliance' => $appliance,
            'location' => $office,
            'lastUpdate'=> (new \DateTime('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s P'),
        ])->save();

        return $moduleItem;
    }

    /**
     * @param Appliance $appliance
     * @param Collection $usedModules
     */
    protected function processUnUsedModulesDataSet(Appliance $appliance, Collection $usedModules)
    {
        $appliance->refresh();
        $dbModules = $appliance->modules;
        if (0 < $dbModules->count()) {
            foreach ($dbModules as $dbModule) {
                if (!$usedModules->existsElement(['serialNumber' => $dbModule->serialNumber])) {
                    $dbModule->notFound();
                    $dbModule->notUse();
                    $dbModule->save();
                }
            }
        }
    }

    /**
     * @param Appliance $appliance
     * @param $ipAddress
     */
    protected function processDataPortDataSet(Appliance $appliance, $ipAddress)
    {
        $vrf = $this->processVrfDataSet();
        $dataPort = DataPort::findByIpVrf($ipAddress, $vrf);

        if ($dataPort instanceof DataPort) {
            $dataPort->fill([
                'appliance' => $appliance,
                'vrf' => $vrf,
                'isManagement' => true,
            ])->save();
        }

        if (!($dataPort instanceof DataPort)) {
            $portType = $this->processPortTypeDataSet();

            (new DataPort())->fill([
                'ipAddress' => $ipAddress,
                'portType' => $portType,
                'appliance' => $appliance,
                'vrf' => $vrf,
                'isManagement' => true,
            ])->save();
        }
    }

    /**
     * @return Vrf
     */
    protected function processVrfDataSet()
    {
//        $vrf = $vrf ?? Vrf::instanceGlobalVrf();  // TODO: Добавить в обработку $srcData->vrf
        return Vrf::instanceGlobalVrf();
    }

    /**
     * @return DPortType|bool
     */
    protected function processPortTypeDataSet()
    {
        $portTypeDefault = 'Ethernet';  // TODO: Возможно в будущем будем передавать $portType в запросе, а пока так

        $portType = DPortType::findByType($portTypeDefault);
        if (!($portType instanceof DPortType)) {
            $portType = (new DPortType())
                ->fill([
                    'type' => $portTypeDefault,
                ])
                ->save();
        }

        return $portType;
    }

    protected function processErrorDataSet()
    {
        $logger = new Logger('ErrDS');
        $logger->pushHandler(new StreamHandler(ROOT_PATH . '/Logs/surveyOfAppliances.log', Logger::DEBUG));
        $logger->error($this->dataSet->ip . ' ->> ' . $this->dataSet->hostname . ' ->> ' . $this->dataSet->message);

        return true;
    }

    /**
     * @throws Exception
     */
    protected function verifyDataSet()
    {
        if (0 == count($this->dataSet)) {
            throw new Exception('DATASET: Empty an input dataset');
        }
        if (!isset($this->dataSet->dataSetType)) {
            throw new Exception('DATASET: No field dataSetType');
        }
        if (empty($this->dataSet->dataSetType)) {
            throw new Exception('DATASET: Empty dataSetType');
        }

        if (self::DST_APPLIANCE == $this->dataSet->dataSetType) {
            $this->verifyApplianceDataSet($this->dataSet);
        }

        if (self::DST_CLUSTER == $this->dataSet->dataSetType) {
            $this->verifyClusterDataSet();
        }

        if (self::DST_ERROR == $this->dataSet->dataSetType) {
            $this->verifyErrorDataSet();
        }
    }

    /**
     * @throws Exception
     */
    protected function verifyClusterDataSet()
    {
        if (!isset($this->dataSet->hostname)) {
            throw new Exception('DATASET: No field hostname for cluster');
        }
        if (empty($this->dataSet->clusterAppliances)) {
            throw new Exception('DATASET: Empty clusterAppliances');
        }

        foreach ($this->dataSet->clusterAppliances as $dataSetAppliance) {
            $this->verifyApplianceDataSet($dataSetAppliance);
        }
    }

    /**
     * @param $dataSet
     * @throws MultiException
     */
    protected function verifyApplianceDataSet($dataSet)
    {
        $errors = new MultiException();

        if (!isset($dataSet->LotusId)) {
            $errors->add(new Exception('DATASET: No field LotusId'));
        }
        if (empty($dataSet->LotusId)) {
            $errors->add(new Exception('DATASET: Empty LotusId'));
        }
        if (!is_numeric($dataSet->LotusId)) {
            $errors->add(new Exception('DATASET: LotusId is not valid'));
        }
        if (!isset($dataSet->platformVendor)) {
            $errors->add(new Exception('DATASET: No field platformVendor'));
        }
        if (!isset($dataSet->platformSerial)) {
            $errors->add(new Exception('DATASET: No field platformSerial'));
        }
        if (!isset($dataSet->applianceType)) {
            $errors->add(new Exception('DATASET: No field applianceType'));
        }
        if (!isset($dataSet->applianceModules)) {
            $errors->add(new Exception('DATASET: No field applianceModules'));
        }
        if (!empty($dataSet->applianceModules)) {
            foreach ($dataSet->applianceModules as $moduleDataset) {

                if (!isset($moduleDataset->product_number)) {
                    $errors->add(new Exception('DATASET: No field applianceModule->product_number'));
                }
                if (!isset($moduleDataset->serial)) {
                    $errors->add(new Exception('DATASET: No field applianceModule->serial'));
                }
                if (!isset($moduleDataset->description)) {
                    $errors->add(new Exception('DATASET: No field applianceModule->description'));
                }
                if (('' === $moduleDataset->serial) || ('' === $moduleDataset->product_number)) {
                    $errors->add(new Exception('DATASET: Empty applianceModule->serial or applianceModule->product_number'));
                }
            }
        }
        if (!isset($dataSet->applianceSoft)) {
            $errors->add(new Exception('DATASET: No field applianceSoft'));
        }
        if (!isset($dataSet->softwareVersion)) {
            $errors->add(new Exception('DATASET: No field softwareVersion'));
        }
        if (!isset($dataSet->hostname)) {
            $errors->add(new Exception('DATASET: No field hostname'));
        }
        if (!isset($dataSet->ip)) {
            $errors->add(new Exception('DATASET: No field ip'));
        }
        if (!isset($dataSet->chassis)) {
            $errors->add(new Exception('DATASET: No field chassis'));
        }

        // Если DataSet не валидный, то заканчиваем работу
        if (0 < $errors->count()) {
            throw $errors;
        }
    }

    /**
     * @throws Exception
     */
    protected function verifyErrorDataSet()
    {
        if (!isset($this->dataSet->ip)) {
            throw new Exception('DATASET: No field ip');
        }
        if (!isset($this->dataSet->hostname)) {
            throw new Exception('DATASET: No field hostname');
        }
        if (!isset($this->dataSet->message)) {
            throw new Exception('DATASET: No field message');
        }
    }

    /**
     * Заблокировать db.lock файл
     *
     * @return bool
     * @throws Exception
     */
    protected function dbLock()
    {
        $this->dbLockFile = fopen(self::DBLOCKFILE, 'w');

        if (false === $this->dbLockFile) {
            throw new Exception('Can not open the lock file');
        }

        $blockedFile = flock($this->dbLockFile, LOCK_EX | LOCK_NB);

        $n = self::ITERATIONS; // Кол-во попыток доступа к db.lock
        while (false === $blockedFile && 0 !== $n--) {
            usleep(self::SLEEPTIME);
            $blockedFile = flock($this->dbLockFile, LOCK_EX | LOCK_NB);
        }

        if (false === $blockedFile) {
            fclose($this->dbLockFile);
            return false;
        }

        return true;
    }

    /**
     * Разблокировать db.lock файл
     *
     * @return bool
     */
    protected function dbUnLock()
    {
        flock($this->dbLockFile, LOCK_UN);
        fclose($this->dbLockFile);

        return true;
    }
}
