<?php

require_once __DIR__ . '/../../protected/autoload.php';
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../protected/boot.php';
require_once __DIR__ . '/../DbTrait.php';
require_once __DIR__ . '/../EnvironmentTrait.php';


class RServerTest extends \PHPUnit\Framework\TestCase
{
    use DbTrait;
    use EnvironmentTrait;

    public function providerDbLockedDataSetAppliance()
    {
        return [
            [
                '{
                    "platformSerial":"testPS",
                    "applianceModules":
                    [
                        {
                            "serial":"sn 1",
                            "product_number":"pr_num 1",
                            "description":"desc 1"
                        },
                        {
                            "serial":"sn 2",
                            "product_number":"pr_num 2",
                            "description":"desc 2"
                        },
                        {
                            "serial":"sn 3",
                            "product_number":"pr_num 3",
                            "description":"desc 3"
                        },
                        {
                            "serial":"sn 4",
                            "product_number":"pr_num 4",
                            "description":"desc 4"
                        },
                        {
                            "serial":"sn 5",
                            "product_number":"pr_num 5",
                            "description":"desc 5"
                        }
                    ],
                    "LotusId":"1",
                    "hostname":"host",
                    "applianceType":"device",
                    "softwareVersion":"ver soft",
                    "chassis":"ch 1",
                    "platformTitle":"pl_title 1",
                    "ip":"10.100.240.195/24",
                    "applianceSoft":"soft",
                    "platformVendor":"CISCO"
                }',
                400
            ]
        ];
    }

    /**
     * @param $jdataSet
     * @param $codeResult
     *
     * @dataProvider providerDbLockedDataSetAppliance
     */
    public function testDbLocked($jdataSet, $codeResult)
    {
        $this->markTestSkipped();

        $fistDbLockFileResource = fopen(ROOT_PATH_PROTECTED . '/db.lock', 'w');
        $this->assertInternalType('resource', $fistDbLockFileResource);
        $this->assertTrue(flock($fistDbLockFileResource, LOCK_EX | LOCK_NB));

        $this->createOffice();
        $resultRequest = $this->pushData($jdataSet);
        $this->assertEquals($codeResult, $resultRequest->httpStatusCode);
        $this->assertEquals('Can not get the lock file', $resultRequest->errors);

        $this->assertTrue(flock($fistDbLockFileResource, LOCK_UN));
        $this->assertTrue(fclose($fistDbLockFileResource));
    }

    public function providerValidDataSetAppliance()
    {
        return [
            [
                '{
                    "platformSerial":"testPS",
                    "applianceModules":
                    [
                        {
                            "serial":"sn 1",
                            "product_number":"pr_num 1",
                            "description":"desc 1"
                        },
                        {
                            "serial":"sn 2",
                            "product_number":"pr_num 2",
                            "description":"desc 2"
                        },
                        {
                            "serial":"sn 3",
                            "product_number":"pr_num 3",
                            "description":"desc 3"
                        },
                        {
                            "serial":"sn 4",
                            "product_number":"pr_num 4",
                            "description":"desc 4"
                        },
                        {
                            "serial":"sn 5",
                            "product_number":"pr_num 5",
                            "description":"desc 5"
                        }
                    ],
                    "LotusId":"1",
                    "hostname":"host",
                    "applianceType":"device",
                    "softwareVersion":"ver soft",
                    "chassis":"ch 1",
                    "platformTitle":"pl_title 1",
                    "ip":"10.100.240.195/24",
                    "applianceSoft":"soft",
                    "platformVendor":"CISCO"
                }',
                202
            ]
        ];
    }

    public function pushData($jsonDataSet)
    {
//        $url = "http://10.99.120.208/rserver/test";
        $url = "http://voice.loc/rServer/test";
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($curl, CURLOPT_POSTFIELDS, $jsonDataSet);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HEADER, false);
        $requestResult = (new \T4\Core\Std())
            ->fill(json_decode(curl_exec($curl)));
        curl_close($curl);

        return $requestResult;
    }

    /**
     * @param $jdataSet
     * @param $codeResult
     *
     * @dataProvider providerValidDataSetAppliance
     */
    public function testValidAppliance($jdataSet, $codeResult)
    {
        // Determine "Location"
        $this->createOffice();
        $resultRequest = $this->pushData($jdataSet);
        $this->assertEquals($codeResult, $resultRequest->httpStatusCode);

        // Determine the validity of the input data format
        $dataSet = json_decode($jdataSet);

        // Find "Vendor"
        $query = (new \T4\Dbal\Query())
            ->select()
            ->from(\App\Models\Vendor::getTableName())
            ->where('"title" = :title')
            ->params([':title' => $dataSet->platformVendor]);
        $vendors = \App\Models\Vendor::findAllByQuery($query);
        $this->assertEquals(1, $vendors->count());
        $vendor = $vendors->first();
        $this->assertInstanceOf(\App\Models\Vendor::class, $vendor);

        // Find "Platform"
        $query = (new \T4\Dbal\Query())
            ->select()
            ->from(\App\Models\Platform::getTableName())
            ->where('"__vendor_id" = :__vendor_id AND "title" = :title')
            ->params([':__vendor_id' => $vendor->getPk(), ':title' => $dataSet->chassis]);
        $platforms = \App\Models\Platform::findAllByQuery($query);
        $this->assertEquals(1, $platforms->count());
        $platform = $platforms->first();
        $this->assertInstanceOf(\App\Models\Platform::class, $platform);

        // Find "PlatformItem"
        $query = (new \T4\Dbal\Query())
            ->select()
            ->from(\App\Models\PlatformItem::getTableName())
            ->where('"__platform_id" = :__platform_id AND "serialNumber" = :serialNumber')
            ->params([':__platform_id' => $platform->getPk(), ':serialNumber' => $dataSet->platformSerial]);
        $platformItems = \App\Models\PlatformItem::findAllByQuery($query);
        $this->assertEquals(1, $platformItems->count());
        $platformItem = $platformItems->first();
        $this->assertInstanceOf(\App\Models\PlatformItem::class, $platformItem);

        // Find "Software"
        $query = (new \T4\Dbal\Query())
            ->select()
            ->from(\App\Models\Software::getTableName())
            ->where('"__vendor_id" = :__vendor_id AND "title" = :title')
            ->params([':__vendor_id' => $vendor->getPk(), ':title' => $dataSet->applianceSoft]);
        $softwares = \App\Models\Software::findAllByQuery($query);
        $this->assertEquals(1, $softwares->count());
        $software = $softwares->first();
        $this->assertInstanceOf(\App\Models\Software::class, $software);

        // Find "SoftwareItem"
        $query = (new \T4\Dbal\Query())
            ->select()
            ->from(\App\Models\SoftwareItem::getTableName())
            ->where('"__software_id" = :__software_id AND "version" = :version')
            ->params([':__software_id' => $software->getPk(), ':version' => $dataSet->softwareVersion]);
        $softwareItems = \App\Models\SoftwareItem::findAllByQuery($query);
        $this->assertEquals(1, $softwareItems->count());
        $softwareItem = $softwareItems->first();
        $this->assertInstanceOf(\App\Models\SoftwareItem::class, $softwareItem);

        // Find "Appliance Type"
        $query = (new \T4\Dbal\Query())
            ->select()
            ->from(\App\Models\ApplianceType::getTableName())
            ->where('"type" = :type')
            ->params([':type' => $dataSet->applianceType]);
        $applianceTypes = \App\Models\ApplianceType::findAllByQuery($query);
        $this->assertEquals(1, $applianceTypes->count());
        $applianceType = $applianceTypes->first();
        $this->assertInstanceOf(\App\Models\ApplianceType::class, $applianceType);

        // Find "Appliance"
        $query = (new \T4\Dbal\Query())
            ->select()
            ->from(\App\Models\Appliance::getTableName())
            ->where('
                "__vendor_id" = :__vendor_id AND
                "__platform_item_id" = :__platform_item_id AND
                "__software_item_id" = :__software_item_id AND
                "__type_id" = :__type_id
            ')
            ->params([
                ':__vendor_id' => $vendor->getPk(),
                ':__platform_item_id' => $platformItem->getPk(),
                ':__software_item_id' => $softwareItem->getPk(),
                ':__type_id' => $applianceType->getPk()
            ]);
        $appliances = \App\Models\Appliance::findAllByQuery($query);
        $this->assertEquals(1, $appliances->count());
        $appliance = $appliances->first();
        $this->assertInstanceOf(\App\Models\Appliance::class, $appliance);

        // Find the "Modules"
        foreach ($dataSet->applianceModules as $moduleDataset) {
            if (empty($moduleDataset->serial) || empty($moduleDataset->product_number)) {
                continue;
            }

            // Find "Module"
            $query = (new \T4\Dbal\Query())
                ->select()
                ->from(\App\Models\Module::getTableName())
                ->where('"__vendor_id" = :__vendor_id AND "title" = :title')
                ->params([':__vendor_id' => $vendor->getPk(), ':title' => $moduleDataset->product_number]);
            $modules = \App\Models\Module::findAllByQuery($query);
            $this->assertEquals(1, $modules->count());
            $module = $modules->first();
            $this->assertInstanceOf(\App\Models\Module::class, $module);

            // Find "ModuleItem"
            $query = (new \T4\Dbal\Query())
                ->select()
                ->from(\App\Models\ModuleItem::getTableName())
                ->where('
                "serialNumber" = :serialNumber AND
                "__module_id" = :__module_id AND
                "__appliance_id" = :__appliance_id
            ')
                ->params([
                    ':serialNumber' => $moduleDataset->serial,
                    ':__module_id' => $module->getPk(),
                    ':__appliance_id' => $appliance->getPk(),
                ]);
            $moduleItems = \App\Models\ModuleItem::findAllByQuery($query);
            $this->assertEquals(1, $moduleItems->count());
            $moduleItem = $moduleItems->first();
            $this->assertInstanceOf(\App\Models\ModuleItem::class, $moduleItem);
        }
        $this->assertEquals(count($dataSet->applianceModules), $appliance->modules->count());

        // Find "DataPortType"
        $portTypeDefault = 'Ethernet';
        $query = (new \T4\Dbal\Query())
            ->select()
            ->from(\App\Models\DPortType::getTableName())
            ->where('"type" = :type')
            ->params([':type' => $portTypeDefault]);
        $portTypes = \App\Models\DPortType::findAllByQuery($query);
        $this->assertEquals(1, $portTypes->count());
        $portType = $portTypes->first();
        $this->assertInstanceOf(\App\Models\DPortType::class, $portType);

        // Find "Vrf"
        $query = (new \T4\Dbal\Query())
            ->select()
            ->from(\App\Models\Vrf::getTableName())
            ->where('"name" = :name')
            ->params([':name' => \App\Models\Vrf::GLOBAL_VRF_NAME]);
        $globalVrfs = \App\Models\Vrf::findAllByQuery($query);
        $this->assertEquals(1, $globalVrfs->count());
        $globalVrf = $globalVrfs->first();
        $this->assertInstanceOf(\App\Models\Vrf::class, $globalVrf);

        // Find "Network"
        $query = (new \T4\Dbal\Query())
            ->select()
            ->from(\App\Models\Network::getTableName())
            ->where('"__vrf_id" = :__vrf_id')
            ->params([':__vrf_id' => $globalVrf->getPk()]);
        $networks = \App\Models\Network::findAllByQuery($query);
        $this->assertEquals(1, $networks->count());
        $network = $networks->first();
        $this->assertInstanceOf(\App\Models\Network::class, $network);

        // Find "DataPort"
        $query = (new \T4\Dbal\Query())
            ->select()
            ->from(\App\Models\DataPort::getTableName())
            ->where('
                "ipAddress" = :ipAddress AND
                "__type_port_id" = :__type_port_id AND
                "__appliance_id" = :__appliance_id AND
                "__network_id" = :__network_id
            ')
            ->params([
                ':ipAddress' => $dataSet->ip,
                ':__type_port_id' => $portType->getPk(),
                ':__appliance_id' => $appliance->getPk(),
                ':__network_id' => $network->getPk()
            ]);
        $dataPorts = \App\Models\DataPort::findAllByQuery($query);
        $this->assertEquals(1, $dataPorts->count());
        $dataPort = $dataPorts->first();
        $this->assertInstanceOf(\App\Models\DataPort::class, $dataPort);
    }

    /**
     * @param $jdataSet
     * @param $codeResult
     *
     * @dataProvider providerValidDataSetAppliance
     *
     * @depends testValidAppliance
     */
    public function testDoubleAppliance($jdataSet, $codeResult)
    {
        // Determine "Location"
        $this->createOffice();

        $resultRequest = $this->pushData($jdataSet);
        $this->assertEquals($codeResult, $resultRequest->httpStatusCode);

        // Determine the validity of the input data format
        $dataSet = json_decode($jdataSet);

        // Find "Vendor"
        $query = (new \T4\Dbal\Query())
            ->select()
            ->from(\App\Models\Vendor::getTableName())
            ->where('"title" = :title')
            ->params([':title' => $dataSet->platformVendor]);
        $vendors = \App\Models\Vendor::findAllByQuery($query);
        $this->assertEquals(1, $vendors->count());
        $vendor = $vendors->first();
        $this->assertInstanceOf(\App\Models\Vendor::class, $vendor);

        // Find "Platform"
        $query = (new \T4\Dbal\Query())
            ->select()
            ->from(\App\Models\Platform::getTableName())
            ->where('"__vendor_id" = :__vendor_id AND "title" = :title')
            ->params([':__vendor_id' => $vendor->getPk(), ':title' => $dataSet->chassis]);
        $platforms = \App\Models\Platform::findAllByQuery($query);
        $this->assertEquals(1, $platforms->count());
        $platform = $platforms->first();
        $this->assertInstanceOf(\App\Models\Platform::class, $platform);

        // Find "PlatformItem"
        $query = (new \T4\Dbal\Query())
            ->select()
            ->from(\App\Models\PlatformItem::getTableName())
            ->where('"__platform_id" = :__platform_id AND "serialNumber" = :serialNumber')
            ->params([':__platform_id' => $platform->getPk(), ':serialNumber' => $dataSet->platformSerial]);
        $platformItems = \App\Models\PlatformItem::findAllByQuery($query);
        $this->assertEquals(1, $platformItems->count());
        $platformItem = $platformItems->first();
        $this->assertInstanceOf(\App\Models\PlatformItem::class, $platformItem);

        // Find "Software"
        $query = (new \T4\Dbal\Query())
            ->select()
            ->from(\App\Models\Software::getTableName())
            ->where('"__vendor_id" = :__vendor_id AND "title" = :title')
            ->params([':__vendor_id' => $vendor->getPk(), ':title' => $dataSet->applianceSoft]);
        $softwares = \App\Models\Software::findAllByQuery($query);
        $this->assertEquals(1, $softwares->count());
        $software = $softwares->first();
        $this->assertInstanceOf(\App\Models\Software::class, $software);

        // Find "SoftwareItem"
        $query = (new \T4\Dbal\Query())
            ->select()
            ->from(\App\Models\SoftwareItem::getTableName())
            ->where('"__software_id" = :__software_id AND "version" = :version')
            ->params([':__software_id' => $software->getPk(), ':version' => $dataSet->softwareVersion]);
        $softwareItems = \App\Models\SoftwareItem::findAllByQuery($query);
        $this->assertEquals(1, $softwareItems->count());
        $softwareItem = $softwareItems->first();
        $this->assertInstanceOf(\App\Models\SoftwareItem::class, $softwareItem);

        // Find "Appliance Type"
        $query = (new \T4\Dbal\Query())
            ->select()
            ->from(\App\Models\ApplianceType::getTableName())
            ->where('"type" = :type')
            ->params([':type' => $dataSet->applianceType]);
        $applianceTypes = \App\Models\ApplianceType::findAllByQuery($query);
        $this->assertEquals(1, $applianceTypes->count());
        $applianceType = $applianceTypes->first();
        $this->assertInstanceOf(\App\Models\ApplianceType::class, $applianceType);

        // Find "Appliance"
        $query = (new \T4\Dbal\Query())
            ->select()
            ->from(\App\Models\Appliance::getTableName())
            ->where('
                "__vendor_id" = :__vendor_id AND
                "__platform_item_id" = :__platform_item_id AND
                "__software_item_id" = :__software_item_id AND
                "__type_id" = :__type_id
            ')
            ->params([
                ':__vendor_id' => $vendor->getPk(),
                ':__platform_item_id' => $platformItem->getPk(),
                ':__software_item_id' => $softwareItem->getPk(),
                ':__type_id' => $applianceType->getPk()
            ]);
        $appliances = \App\Models\Appliance::findAllByQuery($query);
        $this->assertEquals(1, $appliances->count());
        $appliance = $appliances->first();
        $this->assertInstanceOf(\App\Models\Appliance::class, $appliance);

        // Find the "Modules"
        foreach ($dataSet->applianceModules as $moduleDataset) {
            if (empty($moduleDataset->serial) || empty($moduleDataset->product_number)) {
                continue;
            }

            // Find "Module"
            $query = (new \T4\Dbal\Query())
                ->select()
                ->from(\App\Models\Module::getTableName())
                ->where('"__vendor_id" = :__vendor_id AND "title" = :title')
                ->params([':__vendor_id' => $vendor->getPk(), ':title' => $moduleDataset->product_number]);
            $modules = \App\Models\Module::findAllByQuery($query);
            $this->assertEquals(1, $modules->count());
            $module = $modules->first();
            $this->assertInstanceOf(\App\Models\Module::class, $module);

            // Find "ModuleItem"
            $query = (new \T4\Dbal\Query())
                ->select()
                ->from(\App\Models\ModuleItem::getTableName())
                ->where('
                "serialNumber" = :serialNumber AND
                "__module_id" = :__module_id AND
                "__appliance_id" = :__appliance_id
            ')
                ->params([
                    ':serialNumber' => $moduleDataset->serial,
                    ':__module_id' => $module->getPk(),
                    ':__appliance_id' => $appliance->getPk(),
                ]);
            $moduleItems = \App\Models\ModuleItem::findAllByQuery($query);
            $this->assertEquals(1, $moduleItems->count());
            $moduleItem = $moduleItems->first();
            $this->assertInstanceOf(\App\Models\ModuleItem::class, $moduleItem);
        }
        $this->assertEquals(count($dataSet->applianceModules), $appliance->modules->count());

        // Find "DataPortType"
        $portTypeDefault = 'Ethernet';
        $query = (new \T4\Dbal\Query())
            ->select()
            ->from(\App\Models\DPortType::getTableName())
            ->where('"type" = :type')
            ->params([':type' => $portTypeDefault]);
        $portTypes = \App\Models\DPortType::findAllByQuery($query);
        $this->assertEquals(1, $portTypes->count());
        $portType = $portTypes->first();
        $this->assertInstanceOf(\App\Models\DPortType::class, $portType);

        // Find "Vrf"
        $query = (new \T4\Dbal\Query())
            ->select()
            ->from(\App\Models\Vrf::getTableName())
            ->where('"name" = :name')
            ->params([':name' => \App\Models\Vrf::GLOBAL_VRF_NAME]);
        $globalVrfs = \App\Models\Vrf::findAllByQuery($query);
        $this->assertEquals(1, $globalVrfs->count());
        $globalVrf = $globalVrfs->first();
        $this->assertInstanceOf(\App\Models\Vrf::class, $globalVrf);

        // Find "Network"
        $query = (new \T4\Dbal\Query())
            ->select()
            ->from(\App\Models\Network::getTableName())
            ->where('"__vrf_id" = :__vrf_id')
            ->params([':__vrf_id' => $globalVrf->getPk()]);
        $networks = \App\Models\Network::findAllByQuery($query);
        $this->assertEquals(1, $networks->count());
        $network = $networks->first();
        $this->assertInstanceOf(\App\Models\Network::class, $network);

        // Find "DataPort"
        $query = (new \T4\Dbal\Query())
            ->select()
            ->from(\App\Models\DataPort::getTableName())
            ->where('
                "ipAddress" = :ipAddress AND
                "__type_port_id" = :__type_port_id AND
                "__appliance_id" = :__appliance_id AND
                "__network_id" = :__network_id
            ')
            ->params([
                ':ipAddress' => $dataSet->ip,
                ':__type_port_id' => $portType->getPk(),
                ':__appliance_id' => $appliance->getPk(),
                ':__network_id' => $network->getPk()
            ]);
        $dataPorts = \App\Models\DataPort::findAllByQuery($query);
        $this->assertEquals(1, $dataPorts->count());
        $dataPort = $dataPorts->first();
        $this->assertInstanceOf(\App\Models\DataPort::class, $dataPort);
    }

    public function providerInvalidDataSetApplianceError_1()
    {
        return [
            ['{"platformSerial":"testPS" "applianceModules":[{"serial":"sn 1","product_number":"pr_num 1","description":"desc 1"},{"serial":"sn 2","product_number":"pr_num 2","description":"desc 2"}],"LotusId":"1","hostname":"host 223","applianceType":"device","softwareVersion":"ver soft","chassis":"ch 1","platformTitle":"pl_title 1","ip":"10.100.240.195/24","applianceSoft":"soft","platformVendor":"CISCO"}',400],
            ['',400],
        ];
    }

    /**
     * @param $jdataSet
     * @param $codeResult
     *
     * @dataProvider providerInvalidDataSetApplianceError_1
     */
    public function testInvalidDataSetApplianceError_1($jdataSet, $codeResult)
    {
        $resultRequest = $this->pushData($jdataSet);
        $this->assertEquals($codeResult, $resultRequest->httpStatusCode);

        $this->assertEquals('DATASET: Not a valid JSON input dataset', $resultRequest->errors);
    }



    public function providerInvalidDataSetApplianceError_2()
    {
        return [
            ['{"":"testPS", "applianceModules":[{"":"sn 1","":"pr_num 1","":"desc 1"},{"":"sn 2","":"pr_num 2","":"desc 2"}],"":"1","":"host 223","":"device","":"ver soft","":"ch 1","":"pl_title 1","":"10.100.240.195/24","":"soft","":"CISCO"}',400],
        ];
    }

    /**
     * @param $jdataSet
     * @param $codeResult
     *
     * @dataProvider providerInvalidDataSetApplianceError_2
     */
    public function testInvalidDataSetApplianceError_2($jdataSet, $codeResult)
    {
        $resultRequest = $this->pushData($jdataSet);
        $this->assertEquals($codeResult, $resultRequest->httpStatusCode);

        $this->assertEquals(19, count($resultRequest->errors));
    }



    public function providerDelUnUsedModulesDataSet()
    {
        return [
            [
                '{
                    "platformSerial":"testPS",
                    "applianceModules":
                    [
                        {
                            "serial":"sn 1",
                            "product_number":"pr_num 1",
                            "description":"desc 1"
                        },
                        {
                            "serial":"sn 3",
                            "product_number":"pr_num 3",
                            "description":"desc 3"
                        },
                        {
                            "serial":"sn 4",
                            "product_number":"pr_num 4",
                            "description":"desc 4"
                        }
                    ],
                    "LotusId":"1",
                    "hostname":"host",
                    "applianceType":"device",
                    "softwareVersion":"ver soft",
                    "chassis":"ch 1",
                    "platformTitle":"pl_title 1",
                    "ip":"10.100.240.195/24",
                    "applianceSoft":"soft",
                    "platformVendor":"CISCO"
                }',
                202
            ]
        ];
    }

    /**
     * @param $jdataSet
     * @param $codeResult
     *
     * @dataProvider providerDelUnUsedModulesDataSet
     *
     * @depends testValidAppliance
     */
    public function testDelUnUsedModules($jdataSet, $codeResult)
    {
        $appliances = \App\Models\Appliance::findAll();
        $this->assertEquals(1, $appliances->count());
        $appliance = $appliances->first();
        $this->assertInstanceOf(\App\Models\Appliance::class, $appliance);

        // Find "ModuleItem" before Request
        $query = (new \T4\Dbal\Query())
            ->select()
            ->from(\App\Models\ModuleItem::getTableName())
            ->where('
                "__appliance_id" = :__appliance_id
            ')
            ->params([
                ':__appliance_id' => $appliance->getPk(),
            ]);
        $dbModuleItems = \App\Models\ModuleItem::findAllByQuery($query);
        $this->assertEquals(5, $dbModuleItems->count());

        // Send Request for update Appliance's data
        $resultRequest = $this->pushData($jdataSet);
        $this->assertEquals($codeResult, $resultRequest->httpStatusCode);

        // Find "ModuleItem" after Request
        $useModuleItems = \App\Models\ModuleItem::findAllByQuery($query);
        $this->assertEquals(3, $useModuleItems->count());

        // Determine the UNUSED "Modules"
        foreach ($dbModuleItems as $dbModule) {
            if (!$useModuleItems->existsElement(['serialNumber' => $dbModule->serialNumber])) {
                $query = (new \T4\Dbal\Query())
                    ->select()
                    ->from(\App\Models\ModuleItem::getTableName())
                    ->where('"serialNumber" = :serialNumber')
                    ->params([':serialNumber' => $dbModule->serialNumber]);
                $unUsedModuleItems = \App\Models\ModuleItem::findAllByQuery($query);
                $this->assertEquals(1, $unUsedModuleItems->count());
                $unUsedModuleItem = $unUsedModuleItems->first();
                $this->assertEquals(null, $unUsedModuleItem->__appliance_id);
            }
        }
    }



    public function providerAddNewModulesDataSet()
    {
        return [
            [
                '{
                    "platformSerial":"testPS",
                    "applianceModules":
                    [
                        {
                            "serial":"sn 1",
                            "product_number":"pr_num 1",
                            "description":"desc 1"
                        },
                        {
                            "serial":"sn 2",
                            "product_number":"pr_num 2",
                            "description":"desc 2"
                        },
                        {
                            "serial":"sn 3",
                            "product_number":"pr_num 3",
                            "description":"desc 3"
                        },
                        {
                            "serial":"sn 4",
                            "product_number":"pr_num 4",
                            "description":"desc 4"
                        },
                        {
                            "serial":"sn 5",
                            "product_number":"pr_num 5",
                            "description":"desc 5"
                        },
                        {
                            "serial":"sn 6",
                            "product_number":"pr_num 6",
                            "description":"desc 6"
                        },
                        {
                            "serial":"sn 7",
                            "product_number":"pr_num 7",
                            "description":"desc 7"
                        }
                    ],
                    "LotusId":"1",
                    "hostname":"host",
                    "applianceType":"device",
                    "softwareVersion":"ver soft",
                    "chassis":"ch 1",
                    "platformTitle":"pl_title 1",
                    "ip":"10.100.240.195/24",
                    "applianceSoft":"soft",
                    "platformVendor":"CISCO"
                }',
                202
            ]
        ];
    }

    /**
     * @param $jdataSet
     * @param $codeResult
     *
     * @dataProvider providerAddNewModulesDataSet
     *
     * @depends testDelUnUsedModules
     */
    public function testAddNewModules($jdataSet, $codeResult)
    {
        $appliances = \App\Models\Appliance::findAll();
        $this->assertEquals(1, $appliances->count());
        $appliance = $appliances->first();
        $this->assertInstanceOf(\App\Models\Appliance::class, $appliance);

        // Find "ModuleItem" before Request
        $query = (new \T4\Dbal\Query())
            ->select()
            ->from(\App\Models\ModuleItem::getTableName())
            ->where('
                "__appliance_id" = :__appliance_id
            ')
            ->params([
                ':__appliance_id' => $appliance->getPk(),
            ]);
        $dbModuleItems = \App\Models\ModuleItem::findAllByQuery($query);
        $this->assertEquals(3, $dbModuleItems->count());

        // Send Request for update Appliance's data
        $resultRequest = $this->pushData($jdataSet);
        $this->assertEquals($codeResult, $resultRequest->httpStatusCode);

        // Find "ModuleItem" after Request
        $usedModuleItems = \App\Models\ModuleItem::findAllByQuery($query);
        $this->assertEquals(7, $usedModuleItems->count());

        // Find "Vendor"
        $dataSet = json_decode($jdataSet);
        $query = (new \T4\Dbal\Query())
            ->select()
            ->from(\App\Models\Vendor::getTableName())
            ->where('"title" = :title')
            ->params([':title' => $dataSet->platformVendor]);
        $vendors = \App\Models\Vendor::findAllByQuery($query);
        $this->assertEquals(1, $vendors->count());
        $vendor = $vendors->first();
        $this->assertInstanceOf(\App\Models\Vendor::class, $vendor);

        // Find the USED "Modules"
        foreach ($dataSet->applianceModules as $moduleDataset) {

            // Find "Module"
            $query = (new \T4\Dbal\Query())
                ->select()
                ->from(\App\Models\Module::getTableName())
                ->where('"__vendor_id" = :__vendor_id AND "title" = :title')
                ->params([':__vendor_id' => $vendor->getPk(), ':title' => $moduleDataset->product_number]);
            $modules = \App\Models\Module::findAllByQuery($query);
            $this->assertEquals(1, $modules->count());
            $module = $modules->first();
            $this->assertInstanceOf(\App\Models\Module::class, $module);

            // Find "ModuleItem"
            $query = (new \T4\Dbal\Query())
                ->select()
                ->from(\App\Models\ModuleItem::getTableName())
                ->where('
                "serialNumber" = :serialNumber AND
                "__module_id" = :__module_id AND
                "__appliance_id" = :__appliance_id
            ')
                ->params([
                    ':serialNumber' => $moduleDataset->serial,
                    ':__module_id' => $module->getPk(),
                    ':__appliance_id' => $appliance->getPk(),
                ]);
            $moduleItems = \App\Models\ModuleItem::findAllByQuery($query);
            $this->assertEquals(1, $moduleItems->count());
            $moduleItem = $moduleItems->first();
            $this->assertInstanceOf(\App\Models\ModuleItem::class, $moduleItem);
        }
    }
}
