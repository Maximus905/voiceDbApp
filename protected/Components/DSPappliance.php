<?php
namespace App\Components;

use App\Exceptions\DblockException;
use App\Exceptions\LocationException;
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
use T4\Core\Collection;
use T4\Core\Exception;
use T4\Core\MultiException;
use T4\Core\Std;


class DSPappliance extends Std
{
    const SLEEPTIME = 500; // микросекунды
    const ITERATIONS = 6000000; // Колличество попыток получить доступ к db.lock файлу
    const DBLOCKFILE = ROOT_PATH_PROTECTED . '/db.lock';

    protected $dataSet;
    protected $appliance;
    protected $cluster;
    protected $dbLockFile;
    protected $debugLogger;


    /**
     * DSPappliance constructor.
     * @param null $dataSet
     * @param Appliance|null $appliance
     * @param Cluster|null $cluster
     */
    public function __construct($dataSet = null, Appliance $appliance = null, Cluster $cluster = null)
    {
        $this->dataSet = $dataSet;
        $this->appliance = $appliance;
        $this->cluster = $cluster;
//        $this->debugLogger = RLogger::getInstance('DSPappliance', realpath(ROOT_PATH . '/Logs/debug.log'));
    }


    /**
     * @return Appliance
     * @throws Exception
     */
    public function run()
    {
//        $this->debugLogger->info('START: ' . '[ip]=' . $this->dataSet->ip);

        $this->verifyDataSet();
        $this->beforeProcessDataSet();

        try {
            $office = Office::findByLotusId($this->dataSet->LotusId);
            if (!($office instanceof Office)) {
                throw new LocationException('Location not found, LotusId = ' . $this->dataSet->LotusId);
            }
//            $this->debugLogger->info('process: ' . '[ip]=' . $this->dataSet->ip . '; [office]=' . $office->title);


            // Заблокировать DB на запись
            if (false === $this->dbLock()) {
                throw new DblockException('Can not get the lock file');
            }

            Appliance::getDbConnection()->beginTransaction();

            /**
             * << Варианты устройств в БД >>
             *
             * Case "platformSerial - ЕСТЬ, managementIp - ЕСТЬ"  -> полноценное устройство. Ищем устройство по platformSerial
             * Case "platformSerial - ЕСТЬ, managementIp - НЕТ"  -> устройство в составе кластера. Первому устройству в dataset присваиваем managementIp кластера
             * Case "platformSerial - НЕТ, managementIp - ЕСТЬ"  -> пустое устройство заведенное из вэб интерфейса
             *
             */

            // Case "Find appliance by platformSerial"
            if (!($this->appliance instanceof Appliance)) {
                $this->appliance = Appliance::findByVendorTitlePlatformSerial($this->dataSet->platformVendor, $this->dataSet->platformSerial);

                // Нашли по серийнику appliance, значит нашли его $vendor, $platform, $platformItem.
                if ($this->appliance instanceof Appliance) {
                    $vendor = $this->appliance->vendor;
                    $platform = $this->appliance->platform->platform;
                    $platformItem = $this->appliance->platform;

                    // Проверим к какому appliance привязан $this->dataSet->ip.
                    if (!empty($this->dataSet->ip)) {
                        $managementIP = (new IpTools($this->dataSet->ip))->address;
                        $appliance = (DataPort::findByIpVrf($managementIP, Vrf::instanceGlobalVrf()))->appliance;

                        // ЕСЛИ $this->dataSet->ip привязан к другому пустому appliance
                        if ($appliance->getPk() != $this->appliance->getPk() && empty(trim($appliance->platform->serialNumber))) {
                            // ТО Удалим это пустое appliance
                            $appliance->delete();
                        }
                    }
                }
            }

            // Case "Find appliance by management IP"
            if (!($this->appliance instanceof Appliance) && !empty($this->dataSet->ip)) {
                $managementIP = (new IpTools($this->dataSet->ip))->address;
                $appliance = (DataPort::findByIpVrf($managementIP, Vrf::instanceGlobalVrf()))->appliance;
                if (empty(trim($appliance->platform->serialNumber))) {
                    $this->appliance = $appliance;
                }
                if (!empty(trim($appliance->platform->serialNumber))) {
                    $appliance->fill([
                        'inUse' => false,
                    ])->save();
                }
            }

            // Case "Appliance is not found by platformSerial and management IP"
            if (!($this->appliance instanceof Appliance)) {
                $this->appliance = new Appliance();
                $this->appliance->fill([
                    'inUse' => true,
                ]);
            }

            $vendor = $vendor ?? $this->processVendorDataSet($this->dataSet->platformVendor);
//            $this->debugLogger->info('process: ' . '[ip]=' . $this->dataSet->ip . '; [vendor]=' . $vendor->title);

            $platform = $platform ?? $this->processPlatformDataSet($vendor ,$this->dataSet->chassis);
//            $this->debugLogger->info('process: ' . '[ip]=' . $this->dataSet->ip . '; [platform]=' . $platform->title);

            $platformItem = $platformItem ?? $this->processPlatformItemDataSet($platform ,$this->dataSet->platformSerial);
//            $this->debugLogger->info('process: ' . '[ip]=' . $this->dataSet->ip . '; [platformItem]=' . $platformItem->serialNumber);

            $software = $this->processSoftwareDataSet($vendor ,$this->dataSet->applianceSoft);
//            $this->debugLogger->info('process: ' . '[ip]=' . $this->dataSet->ip . '; [software]=' . $software->title);

            $softwareItem = $this->processSoftwareItemDataSet($software ,$this->dataSet->softwareVersion);
//            $this->debugLogger->info('process: ' . '[ip]=' . $this->dataSet->ip . '; [softwareItem]=' . $softwareItem->version);

            $applianceType = $this->processApplianceTypeDataSet($this->dataSet->applianceType);
//            $this->debugLogger->info('process: ' . '[ip]=' . $this->dataSet->ip . '; [applianceType]=' . $applianceType->type);

            $this->appliance->fill([
                'cluster' => $this->cluster,
                'location' => $office,
                'type' => $applianceType,
                'vendor' => $vendor,
                'platform' => $platformItem,
                'software' => $softwareItem,
                'lastUpdate'=> (new \DateTime('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s P'),
                'details' => [
                    'hostname' => $this->dataSet->hostname,
                ],
            ])->save();
//            $this->debugLogger->info('process: ' . '[ip]=' . $this->dataSet->ip . '; [appliance]=' . $this->dataSet->hostname);

            $usedModules = $this->processUsedModulesDataSet($vendor, $office);
            $this->processNotUsedModulesDataSet($usedModules);
            if (!empty($this->dataSet->ip)) {
                $this->processDataPortDataSet();
            }

            Appliance::getDbConnection()->commitTransaction();
            $this->dbUnLock();

        } catch (Exception $e) {
            Appliance::getDbConnection()->rollbackTransaction();
            throw new Exception($e->getMessage());
        } catch (DblockException $e) {
            throw new Exception($e->getMessage());
        } catch (LocationException $e) {
            throw new Exception($e->getMessage());
        }


//        $this->debugLogger->info('END: ' . '[ip]=' . $this->dataSet->ip);

        return $this->appliance;
    }

    protected function beforeProcessDataSet()
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
     * @param $title
     * @return Vendor|bool
     */
    protected function processVendorDataSet($title)
    {
        if (false === $this->appliance->isNew() && $title == $this->appliance->vendor->title) {
            return $this->appliance->vendor;
        }

        $vendor = Vendor::findByTitle($title);

        if (!($vendor instanceof Vendor)) {
            $vendor = (new Vendor())
                ->fill([
                    'title' => $this->dataSet->platformVendor
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
        if (false === $this->appliance->isNew() && $vendor->title == $this->appliance->vendor->title && $title == $this->appliance->platform->platform->title) {
            return $this->appliance->platform->platform;
        }

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
        $platformItem = (false === $this->appliance->isNew() && $this->appliance->platform instanceof PlatformItem) ? $this->appliance->platform : (new PlatformItem());

        $platformItem->fill([
                'platform' => $platform,
                'serialNumber' => $serialNumber
            ])
            ->save();

        return $platformItem;
    }

    /**
     * @param Vendor $vendor
     * @param $title
     * @return Software|bool
     */
    protected function processSoftwareDataSet(Vendor $vendor, $title)
    {
        if (false === $this->appliance->isNew() && $vendor->title == $this->appliance->vendor->title && $title == $this->appliance->software->software->title) {
            return $this->appliance->software->software;
        }

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
        $softwareItem = (false === $this->appliance->isNew()) ? $this->appliance->software : (new SoftwareItem());

        $softwareItem->fill([
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
        if (false === $this->appliance->isNew() && $type == $this->appliance->type->type) {
            return $this->appliance->type;
        }

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
     * @param Vendor $vendor
     * @param Office $office
     * @return Collection
     */
    protected function processUsedModulesDataSet(Vendor $vendor, Office $office)
    {
        $usedModules = new Collection();

        foreach ($this->dataSet->applianceModules as $moduleDataSet) {
            $module = $this->processModuleDataSet($vendor, $moduleDataSet->product_number, $moduleDataSet->description);
            $moduleItem = $this->processModuleItemDataSet($office, $module, $moduleDataSet->serial);

//            $this->debugLogger->info('process: ' . '[ip]=' . $this->dataSet->ip . '; [useModule]=' . $moduleItem->serialNumber);

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
                ])->save();
        }

        return $module;
    }

    /**
     * @param Office $office
     * @param Module $module
     * @param $serialNumber
     * @return ModuleItem
     */
    protected function processModuleItemDataSet(Office $office, Module $module, $serialNumber)
    {
        $moduleItem = ModuleItem::findByVendorSerial($module->vendor->title, $serialNumber);

        $moduleItem = ($moduleItem instanceof ModuleItem) ? $moduleItem : (new ModuleItem());
        $moduleItem->found();
        $moduleItem->fill([
            'module' => $module,
            'appliance' => $this->appliance,
            'location' => $office,
            'lastUpdate'=> (new \DateTime('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s P'),
        ]);
        if (true === $moduleItem->isNew()) {
            $moduleItem->fill([
                'serialNumber' => $serialNumber,
                'inUse' => true,
            ]);
        }
        $moduleItem->save();

        return $moduleItem;
    }

    /**
     * @param Collection $usedModules
     */
    protected function processNotUsedModulesDataSet(Collection $usedModules)
    {
        $this->appliance->refresh();
        $dbModules = $this->appliance->modules;
        if (0 < $dbModules->count()) {
            foreach ($dbModules as $dbModule) {
                if (!$usedModules->existsElement(['serialNumber' => $dbModule->serialNumber])) {
                    $dbModule->notFound();
                    $dbModule->notUse();
                    $dbModule->save();

//                    $this->debugLogger->info('process: ' . '[ip]=' . $this->dataSet->ip . '; [notUsedModule]=' . $dbModule->serialNumber);
                }
            }
        }
    }

    protected function processDataPortDataSet()
    {
        $ipAddress = (new IpTools($this->dataSet->ip))->address;

        $vrf = $this->processVrfDataSet();
//        $this->debugLogger->info('process: ' . '[ip]=' . $this->dataSet->ip . '; [vrf]=' . $vrf->name);

        $dataPort = DataPort::findByIpVrf($ipAddress, $vrf);

        if ($dataPort instanceof DataPort) {
            $dataPort->fill([
                'appliance' => $this->appliance,
                'macAddress' => ($this->dataSet->macAddress) ?? '',
                'vrf' => $vrf,
                'isManagement' => true,
            ])->save();
        }

        if (!($dataPort instanceof DataPort)) {
            $portType = $this->processPortTypeDataSet();

            (new DataPort())->fill([
                'ipAddress' => $ipAddress,
                'macAddress' => ($this->dataSet->macAddress) ?? '',
                'portType' => $portType,
                'appliance' => $this->appliance,
                'vrf' => $vrf,
                'isManagement' => true,
            ])->save();
        }

//        $this->debugLogger->info('process: ' . '[ip]=' . $this->dataSet->ip . '; [dataPort]=' . $dataPort->ipAddress);
    }

    /**
     * @return Vrf
     */
    protected function processVrfDataSet()
    {
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

    /**
     * @throws Exception|MultiException
     */
    protected function verifyDataSet()
    {
        if (0 == count($this->dataSet)) {
            throw new Exception('DATASET: Empty an input dataset');
        }

        $errors = new MultiException();

        if (!isset($this->dataSet->LotusId)) {
            $errors->add(new Exception('DATASET: No field LotusId'));
        }
        if (empty($this->dataSet->LotusId)) {
            $errors->add(new Exception('DATASET: Empty LotusId'));
        }
        if (!is_numeric($this->dataSet->LotusId)) {
            $errors->add(new Exception('DATASET: LotusId is not valid'));
        }
        if (empty($this->dataSet->platformVendor)) {
            $errors->add(new Exception('DATASET: Empty or No field platformVendor'));
        }
        if (empty($this->dataSet->platformSerial)) {
            $errors->add(new Exception('DATASET: Empty or No field platformSerial'));
        }
        if (!isset($this->dataSet->applianceType)) {
            $errors->add(new Exception('DATASET: No field applianceType'));
        }
        if (!isset($this->dataSet->applianceModules)) {
            $errors->add(new Exception('DATASET: No field applianceModules'));
        }
        if (!empty($this->dataSet->applianceModules)) {
            foreach ($this->dataSet->applianceModules as $moduleDataset) {

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
        if (!isset($this->dataSet->applianceSoft)) {
            $errors->add(new Exception('DATASET: No field applianceSoft'));
        }
        if (!isset($this->dataSet->softwareVersion)) {
            $errors->add(new Exception('DATASET: No field softwareVersion'));
        }
        if (!isset($this->dataSet->hostname)) {
            $errors->add(new Exception('DATASET: No field hostname'));
        }
        if (!isset($this->dataSet->ip)) {
            $errors->add(new Exception('DATASET: No field ip'));
        }
        if (!isset($this->dataSet->chassis)) {
            $errors->add(new Exception('DATASET: No field chassis'));
        }

        // Если DataSet не валидный, то заканчиваем работу
        if (0 < $errors->count()) {
            throw $errors;
        }
    }
}
