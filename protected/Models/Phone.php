<?php
namespace App\Models;

use App\Components\AxlClient;
use App\Components\DSPappliance;
use App\Components\RisPortClient;
use App\Components\RLogger;
use T4\Core\Collection;
use T4\Core\Exception;
use T4\Core\Std;

class Phone extends Appliance
{
    const PHONE = 'phone';
    const RISPHONETYPE = 'Phone';
    const MAXRETURNEDDEVICES = 1000; // max 1000 (ограничение RisPort Service)
    const MAXREQUESTSCOUNT = 15; // ограничение RisPort Service
    const TIMEINTERVAL = 60; // секунды
    const ALLMODELS = 255; // All phone's models
    const PHONESTATUS = 'Registered';
    const PHONESOFT = 'Phone Soft';
    const VENDOR = 'CISCO'; // Todo - пока так

    protected $appliance;
    protected $phoneInfo;
    protected $debugLogger;


    public function __construct()
    {
        $this->debugLogger = RLogger::getInstance('Phone', realpath(ROOT_PATH . '/Logs/debug.log'));
    }


    /**
     * @param string $ip
     * @return Collection
     */
    public static function getAllFromCucm(string $ip)
    {
        $logger = RLogger::getInstance('Phone', realpath(ROOT_PATH . '/Logs/debug.log'));

        // Get list of all subscribers and publisher in the cluster
        $listAllProcessNodes = self::getListAllCucmNodes($ip);

        // Получить данные по телефонам из cucm используя AXL
        $axlPhones = self::getAllFromCucmAxl($ip);

        // Получить данные по зарегистрированным телефонам из cucm используя RisPort
        $registeredPhones = self::getAllFromCucmRis($ip, $axlPhones, $listAllProcessNodes);

        // Добавить недостающие поля из AxlPhones в зарегистрированные телефоны из RisPhones
        foreach ($registeredPhones as $phone) {
            $axlPhone = $axlPhones->findByAttributes(['device' => $phone->name]);

            if (!is_null($axlPhone)) {
                $phone->fill([
                    'css' => $axlPhone->css,
                    'devicePool' => $axlPhone->dpool,
                    'prefix' => $axlPhone->prefix,
                    'phoneDN' => $axlPhone->dnorpattern,
                    'alertingName' => $axlPhone->fio,
                    'partition' => $axlPhone->pt,
                    'model' => $axlPhone->type,
                ]);
            } else {
                $logger->info('PHONE: ' . '[name]=' . $phone->ipAddress . ' is not found in AXL');
            }
        }

        // Опросить кажждый телефон по его IP через WEB Interface - DeviceInformationX
        foreach ($registeredPhones as $phone) {
            $phoneData = simplexml_load_file('http://' . $phone->ipAddress . '/DeviceInformationX');
            if (false !== $phoneData) {
                $phone->fill([
                    'macAddress' => $phoneData->MACAddress->__toString(),
                    'serialNumber' => $phoneData->serialNumber->__toString(),
                    'modelNumber' => $phoneData->modelNumber->__toString(),
                    'versionID' => $phoneData->versionID->__toString(),
                    'appLoadID' => $phoneData->appLoadID->__toString(),
                ]);
            } else {
                $logger->info('PHONE: ' . '[name]=' . $phone->ipAddress . ' does not have web access');
            }
        }

        // Возвращать только те телефоны по которым были ответы от AXL, RIS, WEB
        return $registeredPhones->filter(
            function ($phone) {
                return isset($phone->versionID) && isset($phone->model) && isset($phone->ipAddress);
            }
        );
    }


    /**
     * Получить данные по зарегистрированным телефонам из cucm используя RisPort
     *
     * @return Collection
     */
    protected static function getAllFromCucmRis(string $ip, Collection $phones, Collection $nodes)
    {
        $ris = (new RisPortClient($ip))->connection;

        // ЕСЛИ кол-во опрашиваемых ($phones) телефонов БОЛЬШЕ, чем кол-во (MAXRETURNEDDEVICES) отдаваемых callmanager за один запрос,
        // ТО опрашивать callmanager будем по телефонам из коллекции $phones в несколько запросов (не более 15 запросов в минуту)
        // 1 запрос - кол-во телефонов = MAXRETURNEDDEVICES
        $registeredPhones = new Collection();
        if (self::MAXRETURNEDDEVICES < $phones->count()) {
            $phonesCount = 0; // кол-во телефонов в запросе
            $requestsCount = 0; // кол-во запросов
            foreach ($phones as $phone) {
                $items[] = ['Item' => $phone->device];
                $phonesCount++;

                if (self::MAXRETURNEDDEVICES == $phonesCount) {
                    // На старте включаем секундомер
                    if (0 === $requestsCount) {
                        $startTime = (int)microtime(true);
                        $currentCountOfTime = (int)microtime(true) - $startTime;
                    }

                    // Считаем кол-во запросов
                    $requestsCount++;

                    // ЕСЛИ  кол-во времени прошедшее с момента первого запроса превысило self::TIMEINTERVAL
                    // ТО начинаем считать запросы заново
                    if ($currentCountOfTime >= self::TIMEINTERVAL) {
                        $requestsCount = 1;
                        $startTime = (int)microtime(true);
                    } else {

                        // ЕСЛИ кол-во запросов превысило self::MAXREQUESTSCOUNT за время меньшее. чем self::TIMEINTERVAL
                        // ТО ждем до self::MAXREQUESTSCOUNT и начинаем считать запросы заново
                        if ($requestsCount > self::MAXREQUESTSCOUNT) {
                            sleep(self::TIMEINTERVAL - $currentCountOfTime);
                            $requestsCount = 1;
                            $startTime = (int)microtime(true);
                        }
                    }

                    $risPhones = $ris->SelectCmDevice('',[
                        'MaxReturnedDevices' => self::MAXRETURNEDDEVICES,
                        'Class' => self::RISPHONETYPE,
                        'Model' => self::ALLMODELS,
                        'Status' => self::PHONESTATUS,
                        'SelectBy' => 'Name',
                        'SelectItems' => $items,
                    ]);
                    foreach (($risPhones['SelectCmDeviceResult'])->CmNodes as $cmNode) {
                        if ('ok' == strtolower($cmNode->ReturnCode)) {
                            $node = $nodes->findByAttributes(['cmNodeIpAddress' => $cmNode->Name]);

                            foreach ($cmNode->CmDevices as $cmDevice) {
                                $registeredPhones->add(
                                    (new self())->fill([
                                        'cmName' => $node->cmNodeName,
                                        'cmIpAddress' => $node->cmNodeIpAddress,
                                        'name' => $cmDevice->Name,
                                        'ipAddress' => $cmDevice->IpAddress,
                                        'description' => $cmDevice->Description,
                                        'status' => $cmDevice->Status,
                                    ])
                                );
                            }
                        }
                    }

                    // Фиксируем кол-во времени прошедшее с момента первого запроса
                    $currentCountOfTime = (int)microtime(true) - $startTime;

                    $phonesCount = 0;
                    $items = [];
                }
            }
        } else {
            // ЕСЛИ кол-во опрашиваемых телефонов МЕНЬШЕ, чем кол-во (MAXRETURNEDDEVICES) отдаваемых callmanager за один запрос,
            // ТО делаем выборку всех зарегистрированных на callmanager телефонов одним запросом
            $risPhones = $ris->SelectCmDevice('',[
                'Class' => self::RISPHONETYPE,
                'Model' => self::ALLMODELS,
                'Status' => self::PHONESTATUS,
                'SelectBy' => 'Name',
                'SelectItems' => [['Item' => '*']],
            ]);
            foreach (($risPhones['SelectCmDeviceResult'])->CmNodes as $cmNode) {
                if ('ok' == strtolower($cmNode->ReturnCode)) {
                    $node = $nodes->findByAttributes(['cmNodeIpAddress' => $cmNode->Name]);

                    foreach ($cmNode->CmDevices as $cmDevice) {
                        $registeredPhones->add(
                            (new self())->fill([
                                'cmName' => $node->cmNodeName,
                                'cmIpAddress' => $node->cmNodeIpAddress,
                                'name' => $cmDevice->Name,
                                'ipAddress' => $cmDevice->IpAddress,
                                'description' => $cmDevice->Description,
                                'status' => $cmDevice->Status,
                            ])
                        );
                    }
                }
            }
        }

        return $registeredPhones;
    }


    /**
     * Получить данные по телефонам из cucm используя AXL
     *
     * @param string $ip
     * @return Collection
     */
    protected static function getAllFromCucmAxl(string $ip)
    {
        $axl = (new AxlClient($ip))->connection;

        // Получить данные по телефонам из cucm
        $phones = $axl->ExecuteSQLQuery(['sql' => '
                    SELECT d."name" AS Device,
                          d."description",
                          css."name" AS css,
                          css2."name" AS name_off_clause,
                          dp."name" AS dPool,
                          n2."dnorpattern" AS prefix,
                          n."dnorpattern",
                          n."alertingname" AS FIO,
                          partition."name" AS pt,
                          tm."name" AS type
                    FROM device AS d
                    INNER JOIN callingsearchspace AS css ON css."pkid" = d."fkcallingsearchspace"
                    INNER JOIN devicenumplanmap AS dmap ON dmap."fkdevice" = d."pkid" AND d."tkclass" = 1
                    INNER JOIN typemodel AS tm ON d."tkmodel" = tm."enum"
                    INNER JOIN numplan AS n ON dmap."fknumplan" = n."pkid"
                    INNER JOIN routepartition AS partition ON partition."pkid" = n."fkroutepartition"
                    INNER JOIN DevicePool AS dp ON dp."pkid" = d.fkDevicePool
                    INNER JOIN callingsearchspace AS css2 ON css2."clause" LIKE "%" || partition."name" || "%"
                    INNER JOIN numplan AS n2 ON n2."fkcallingsearchspace_translation" = css2."pkid"
                          WHERE n2."tkpatternusage" = 3 AND
                                n2."dnorpattern" LIKE "5%"
                '])->return->row;

        $axlPhones = new Collection();
        foreach ($phones as $phone) {
            $axlPhones->add($phone);
        }

        return $axlPhones;
    }


    /**
     * @return Collection
     */
    protected static function getListAllCucmNodes(string $ip)
    {
        $axl = (new AxlClient($ip))->connection;

        // Get all CmNodes from the publisher
        $listCmNodes = new Collection();
        $cmServers = ($axl->ListAllProcessNodes())->return->processNode;
        foreach ($cmServers as $server) {
            $listCmNodes->add((new Std())
                ->fill([
                    'cmNodeIpAddress' => $server->name,
                    'cmNodeName' => $server->description,
                ])
            );
        }

        return $listCmNodes;
    }


    /**
     * @param array $options
     * @return Collection $appliances
     */
    public static function findAll($options = [])
    {
        return self::filterPhones(parent::findAll($options));
    }

    /**
     * @param mixed $value
     * @return null|Appliance
     */
    public static function findByPK($value)
    {
        $appliance = parent::findByPK($value);
        return (self::PHONE == $appliance->type->type) ? $appliance : null;
    }

    /**
     * @param Collection $appliances
     * @return Collection $appliances
     */
    protected static function filterPhones(Collection $appliances)
    {
        $type = self::PHONE;
        return $appliances->filter(
            function ($appliance) use($type) {
                return $type == $appliance->type->type;
            }
        );
    }


    /**
     * @return bool
     * @throws Exception
     */
    protected function validate()
    {
        if (!isset($this->cmName)) {
            throw new Exception('PHONE: No field cmName');
        }
        if (!isset($this->cmIpAddress)) {
            throw new Exception('PHONE: No field cmIpAddress');
        }
        if (empty($this->name)) {
            throw new Exception('PHONE: Empty or No field name');
        }
        if (!isset($this->ipAddress)) {
            throw new Exception('PHONE: No field ipAddress');
        }
        if (!isset($this->description)) {
            throw new Exception('PHONE: No field description');
        }
        if (!isset($this->css)) {
            throw new Exception('PHONE: No field css');
        }
        if (!isset($this->devicePool)) {
            throw new Exception('PHONE: No field devicePool');
        }
        if (!isset($this->prefix)) {
            throw new Exception('PHONE: No field prefix');
        }
        if (!isset($this->phoneDN)) {
            throw new Exception('PHONE: No field phoneDN');
        }
        if (!isset($this->alertingName)) {
            throw new Exception('PHONE: No field alertingName');
        }
        if (!isset($this->partition)) {
            throw new Exception('PHONE: No field partition');
        }
        if (!isset($this->model)) {
            throw new Exception('PHONE: No field model');
        }

        return true;
    }


    /**
     * @return $this|bool
     * @throws Exception
     */
    public function save()
    {
        if ($this->validate()) {
            $this->debugLogger->info('START: ' . '[name]=' . $this->ipAddress);

            // Find the location by the cucm's IP address
            $cucmLocation = (parent::findByManagementIP($this->cmIpAddress))->location;
            if (!($cucmLocation instanceof Office)) {
                throw new Exception('PHONE: Location not found. CucmIP = ' . $this->cmIpAddress);
            }

            $this->debugLogger->info('process: ' . '[name]=' . $this->ipAddress . '; [office]=' . $cucmLocation->title);

            // Create a DataSet for a phone(appliance)
            $softwareVersion = (1 == preg_match('~6921~', $this->model)) ? $this->appLoadID : $this->versionID;
            $macAddress = ($this->macAddress) ?? substr($this->name,-12);
            $macAddress = implode('.', [
                substr($macAddress,0,4),
                substr($macAddress,4,4),
                substr($macAddress,8,4),
            ]);
            $phoneDataSet = (new Std())->fill([
                'applianceType' => self::PHONE,
                'platformVendor' => self::VENDOR,
                'platformTitle' => ($this->modelNumber) ?? $this->model,
                'platformSerial' => ($this->serialNumber) ?? $this->name,
                'applianceSoft' => self::PHONESOFT,
                'softwareVersion' => $softwareVersion,
                'ip' => $this->ipAddress,
                'macAddress' => $macAddress,
                'LotusId' => $cucmLocation->lotusId,
                'hostname' => '',
                'chassis' => ($this->modelNumber) ?? $this->model,
                'applianceModules' => [],
            ]);


            // IF найден PhoneInfo по его имени (значит нашли и Phone)
            // THEN Update Phone
            if (($this->phoneInfo = PhoneInfo::findByColumn('name', $this->name)) instanceof PhoneInfo) {
                $this->appliance = (new DSPappliance($phoneDataSet, $this->phoneInfo->phone))->run();
            } else {
                $this->appliance = (new DSPappliance($phoneDataSet))->run();
                $this->phoneInfo = new PhoneInfo();
            }

            $this->debugLogger->info('process: ' . '[name]=' . $this->ipAddress . '; [phone]= OK');

            // Update PhoneInfo
            $this->phoneInfo->fill([
                'phone' => $this->appliance,
                'model' => $this->model,
                'name' => $this->name,
                'prefix' => preg_replace('~\..+~','',$this->prefix),
                'phoneDN' => $this->phoneDN,
                'status' => $this->status,
                'description' => $this->description,
                'css' => $this->css,
                'devicePool' => $this->devicePool,
                'alertingName' => $this->alertingName,
                'partition' => $this->partition,
            ])->save();

            $this->debugLogger->info('process: ' . '[name]=' . $this->ipAddress . '; [phoneInfo]= OK');
            $this->debugLogger->info('END: ' . '[name]=' . $this->ipAddress);

            return $this;
        } else {
            return false;
        }
    }


}
