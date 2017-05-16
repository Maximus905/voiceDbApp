<?php

namespace App\Controllers;

use App\Components\WebRequest;
use App\Models\Appliance;
use App\Models\ApplianceType;
use App\Models\DataPort;
use App\Models\DPortType;
use App\Models\Module;
use App\Models\ModuleItem;
use App\Models\Network;
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
use T4\Mvc\Controller;

class RServer extends Controller
{
    /**
     * Warning: Используется только для ТЕСТОВ
     */
    public function actionTest()
    {
        $this->app->db->default = $this->app->db->phpUnitTest;
        $this->actionDefault();
    }

    public function actionDefault()
    {
        define('SLEEPTIME', 100000); // микросекунды
        define('ITERATIONS', 520); // Колличество попыток получить доступ к db.lock файлу

//        $startTime = microtime(true);

        $logger = new Logger('RServer');
        $logfile = ROOT_PATH . '/Logs/surveyOfAppliances.log';
        $logger->pushHandler(new StreamHandler($logfile, Logger::DEBUG));

        try {
            // Getting Datasets in JSON format from php://input
            $jsonData = json_decode(file_get_contents('php://input'));
            $srcData = (new Std())->fill($jsonData);

            // Determine the validity of the input data format
            if (null === $jsonData) {
                throw new Exception('DATASET: Not a valid JSON input dataset');
            }
            if (0 == count($srcData)) {
                throw new Exception('DATASET: Empty an input dataset');
            }

            $errors = new MultiException();

            if (!isset($srcData->LotusId)) {
                $errors->add(new Exception('DATASET: No field LotusId'));
            }
            if (empty($srcData->LotusId)) {
                $errors->add(new Exception('DATASET: Empty LotusId'));
            }
            if (!is_numeric($srcData->LotusId)) {
                $errors->add(new Exception('DATASET: LotusId is not valid'));
            }
            if (!isset($srcData->platformVendor)) {
                $errors->add(new Exception('DATASET: No field platformVendor'));
            }
            if (!isset($srcData->chassis)) {
                $errors->add(new Exception('DATASET: No field chassis'));
            }
            if (!isset($srcData->platformSerial)) {
                $errors->add(new Exception('DATASET: No field platformSerial'));
            }
            if (!isset($srcData->applianceType)) {
                $errors->add(new Exception('DATASET: No field applianceType'));
            }
            if (!isset($srcData->applianceModules)) {
                $errors->add(new Exception('DATASET: No field applianceModules'));
            }
            if (!empty($srcData->applianceModules)) {
                foreach ($srcData->applianceModules as $moduleDataset) {

                    if (!isset($moduleDataset->product_number)) {
                        $errors->add(new Exception('DATASET: No field applianceModule->product_number'));
                    }
                    if (!isset($moduleDataset->serial)) {
                        $errors->add(new Exception('DATASET: No field applianceModule->serial'));
                    }
                    if (!isset($moduleDataset->description)) {
                        $errors->add(new Exception('DATASET: No field applianceModule->description'));
                    }
                }
            }
            if (!isset($srcData->applianceSoft)) {
                $errors->add(new Exception('DATASET: No field applianceSoft'));
            }
            if (!isset($srcData->softwareVersion)) {
                $errors->add(new Exception('DATASET: No field softwareVersion'));
            }
            if (!isset($srcData->hostname)) {
                $errors->add(new Exception('DATASET: No field hostname'));
            }
            if (!isset($srcData->ip)) {
                $errors->add(new Exception('DATASET: No field ip'));
            }

            // Если DataSet не валидный, то заканчиваем работу
            if (0 < $errors->count()) {
                throw $errors;
            }

            // Determine "Location"
            $office = Office::findByLotusId($srcData->LotusId);
            if (!($office instanceof Office)) {
                throw new Exception('Location not found, LotusId = ' . $srcData->LotusId);
            }

//-----------------------------------------------

            // Основная обработка данных в транзакции
            try {
                // Заблокировать db.lock файл
                $dbLockFile = fopen(ROOT_PATH_PROTECTED . '/db.lock', 'w');
                if (false === $dbLockFile) {
                    throw new Exception('Can not open the lock file');;
                }

                $blockedFile = flock($dbLockFile, LOCK_EX | LOCK_NB);

                $n = ITERATIONS; // Кол-во попыток доступа к db.lock
                while (false === $blockedFile && 0 !== $n--) {
                    usleep(SLEEPTIME);
                    $blockedFile = flock($dbLockFile, LOCK_EX | LOCK_NB);
                }

                if (false === $blockedFile) {
                    fclose($dbLockFile);
                    throw new Exception('Can not get the lock file');;
                }

                // Обработка данных
                Appliance::getDbConnection()->beginTransaction();

                // Determine "Vendor"
                $vendor = Vendor::findByTitle($srcData->platformVendor);
                if (!($vendor instanceof Vendor)) {
                    $vendor = (new Vendor())
                        ->fill([
                            'title' => $srcData->platformVendor
                        ])
                        ->save();
                }

                // Process value of "Platform" Title
                $matches = [
                    $vendor->title,
                    '-CHASSIS',
                    'CHASSIS',
                ];
                foreach ($matches as $match) {
                    $srcData->chassis = mb_ereg_replace($match, '', $srcData->chassis, "i");
                }
                if (false === $srcData->chassis) {
                    $errors->add(new Exception('Platform: Title chassis ERROR'));
                }

                // Determine "Platform"
                $requestPlatformTitle = $srcData->chassis;
                $platform = $vendor->platforms->filter(
                    function($platform) use ($requestPlatformTitle) {
                        return $requestPlatformTitle == $platform->title;
                    }
                )->first();
                if (!($platform instanceof Platform)) {
                    $platform = (new Platform())
                        ->fill([
                            'vendor' => $vendor,
                            'title' => $requestPlatformTitle
                        ])
                        ->save();
                }

                // Determine "PlatformItem"
                $requestPlatformSerial = $srcData->platformSerial;
                $platformItem = $platform->platformItems->filter(
                    function($platformItem) use ($requestPlatformSerial) {
                        return $requestPlatformSerial == $platformItem->serialNumber;
                    }
                )->first();
                if (!($platformItem instanceof PlatformItem)) {
                    $platformItem = (new PlatformItem())
                        ->fill([
                            'platform' => $platform,
                            'serialNumber' => $srcData->platformSerial
                        ])
                        ->save();
                }

                // Determine "Software"
                $requestApplianceSoft = $srcData->applianceSoft;
                $software = $vendor->software->filter(
                    function($software) use ($requestApplianceSoft) {
                        return $requestApplianceSoft == $software->title;
                    }
                )->first();
                if (!($software instanceof Software)) {
                    $software = (new Software())
                        ->fill([
                            'vendor' => $vendor,
                            'title' => $srcData->applianceSoft
                        ])
                        ->save();
                }

                // Determine "SoftwareItem"
                $requestSoftwareVersion = $srcData->softwareVersion;
                $softwareItem = $software->softwareItems->filter(
                    function($softwareItem) use ($requestSoftwareVersion) {
                        return $requestSoftwareVersion == $softwareItem->version;
                    }
                )->first();
                if (!($softwareItem instanceof SoftwareItem)) {
                    $softwareItem = (new SoftwareItem())
                        ->fill([
                            'software' => $software,
                            'version' => $srcData->softwareVersion
                        ])
                        ->save();
                }

                // Determine "Appliance Type"
                $applianceType = ApplianceType::findByType($srcData->applianceType);
                if (!($applianceType instanceof ApplianceType)) {
                    $applianceType = (new ApplianceType())
                        ->fill([
                            'type' => $srcData->applianceType
                        ])
                        ->save();
                }

                // Determine "Appliance"
                $appliance = ($platformItem->appliance instanceof Appliance) ? $platformItem->appliance : (new Appliance());
                $appliance->fill([
                    'location' => $office,
                    'type' => $applianceType,
                    'vendor' => $vendor,
                    'platform' => $platformItem,
                    'software' => $softwareItem,
                    'details' => [
                        'hostname' => $srcData->hostname,
                    ]
                ])->save();
                if (!($appliance instanceof Appliance)) {
                    throw new Exception('The appliance is not created');
                }

                // Determine the USED "Modules"
                $usedModules = new Collection();
                foreach ($srcData->applianceModules as $moduleDataset) {

                    // Проверка на отсутствие ключевых данных по модулю == отсутствие модуля у Appliance
                    if (empty($moduleDataset->serial) || empty($moduleDataset->product_number)) {
                        continue;
                    }

                    // Determine "Module"
                    $requestModuleTitle = $moduleDataset->product_number;
                    $module = $vendor->modules->filter(
                        function($module) use ($requestModuleTitle) {
                            return $requestModuleTitle == $module->title;
                        }
                    )->first();

                    if (!($module instanceof Module)) {
                        $module = (new Module())
                            ->fill([
                                'vendor' => $vendor,
                                'title' => $moduleDataset->product_number,
                                'description' => $moduleDataset->description,
                            ])
                            ->save();
                        $vendor->refresh();
                    }

                    // Determine "ModuleItem"
                    $moduleItemSerial = $moduleDataset->serial;
                    $result = $module->moduleItems->filter(
                        function($moduleItem) use ($moduleItemSerial) {
                            return $moduleItemSerial == $moduleItem->serialNumber;
                        }
                    )->first();
                    $moduleItem = ($result instanceof ModuleItem) ? $result : (new ModuleItem());
                    $moduleItem->fill([
                        'module' => $module,
                        'serialNumber' => $moduleItemSerial,
                        'appliance' => $appliance,
                        'location' => $appliance->location,
                    ])->save();

                    $usedModules->add($moduleItem);
                }

                // Determine the UNUSED "Modules"
                $appliance->refresh();
                $dbModules = $appliance->modules;
                if (0 < $dbModules->count()) {
                    foreach ($appliance->modules as $dbModule) {
                        if (!$usedModules->existsElement(['serialNumber' => $dbModule->serialNumber])) {
                            $dbModule->fill([
                                'appliance' => null,
                            ])->save();
                        }
                    }
                }

                // Determine "DataPort"
                $ip = $srcData->ip;
                $vrf = $srcData->vrf ?? Vrf::instanceGlobalVrf();  // TODO: Добавить в обработку $srcData->vrf
                $portTypeDefault = 'Ethernet';  // TODO: Возможно в будущем будем передавать $portType в запросе, а пока так

                $dataPort = DataPort::findByIpVrf($ip, $vrf);

                if (!($dataPort instanceof DataPort)) {

                    $portType = DPortType::findByType($portTypeDefault);
                    if (!($portType instanceof DPortType)) {
                        $portType = (new DPortType())
                            ->fill([
                                'type' => $portTypeDefault,
                            ])
                            ->save();
                    }

                    $dataPort = (new DataPort())
                        ->fill([
                            'ipAddress' => $ip,
                            'portType' => $portType,
                            'appliance' => $appliance,
                            'vrf' => $vrf,
                        ])
                        ->save();
                }
                if (!($dataPort instanceof DataPort)) {
                    throw new Exception('The DataPort is not created');
                }

                Appliance::getDbConnection()->commitTransaction();

                // Снять блокировку файлв db.lock
                flock($dbLockFile, LOCK_UN);
                fclose($dbLockFile);

            } catch (Exception $e) {
                if (false !== $blockedFile) {
                    Appliance::getDbConnection()->rollbackTransaction();
                }

                throw new Exception($e->getMessage());
            }

//-----------------------------------------------

        } catch (MultiException $e) {
            $errors = [];
            foreach ($e as $error) {
                $errors['errors'][] = $error->getMessage();
                $logger->error($srcData->ip . '-> ' . $error->getMessage());
            }

        } catch (Exception $e) {
            $errors['errors'] = $e->getMessage();
            $logger->error($srcData->ip . '-> ' . $e->getMessage());
        }

        // Подготовить и вернуть ответ
        $httpStatusCode = (isset($errors['errors'])) ? 400 : 202; // Bad Request OR Accepted
        $response = (new Collection())->merge(['ip' => $srcData->ip]);
        $response->merge(['httpStatusCode' => $httpStatusCode]);
        if (400 == $httpStatusCode) {
            $response->merge($errors);
        }

//        $stopTime = microtime(true);
//        $logger->error($srcData->ip . '-> ' . ($stopTime - $startTime));

        echo(json_encode($response->toArray()));

        die;
    }

    public function actionLog()
    {
        $logFile = ROOT_PATH . '/Logs/surveyOfAppliances.log';
        $this->data->logs = file($logFile,FILE_IGNORE_NEW_LINES);
    }

    public function actionInfile()
    {
        $rawdata = file_get_contents('php://input');

        $cacheDir = realpath(ROOT_PATH . '/Tmp/Test_test/');
        $mt = explode(' ', microtime());
        $rawmc = explode('.', $mt[0]);
        $mc = $rawmc[1];
        $datetime = date('YmdGis', $mt[1]);
        $fileName = $cacheDir . '\\' . 'item_' . $datetime . $mc . '.json';

        $file = fopen($fileName, 'w+');
        fwrite($file,$rawdata);
        fclose($file);
    }
}
