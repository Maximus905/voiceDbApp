<?php
namespace App\Models;

use App\Components\AxlClient;
use App\Components\DSPappliance;
use App\Components\IpTools;
use App\Components\RisPortClient;
use App\Components\RLogger;
use Sunra\PhpSimple\HtmlDomParser;
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
     * @param string $cucmIp
     * @return Collection
     */
    public static function getAllFromCucm(string $cucmIp)
    {
        $logger = RLogger::getInstance('Phone', realpath(ROOT_PATH . '/Logs/debug.log'));

        // Get list of all subscribers and publisher in the cluster
        $listAllProcessNodes = self::getListAllCucmNodes($cucmIp);

        // Получить данные по телефонам из cucm используя AXL
        $axlPhones = self::getAllFromCucmAxl($cucmIp);

        // Получить данные по зарегистрированным телефонам из cucm используя RisPort
        $registeredPhones = self::getAllFromCucmRis($cucmIp, $axlPhones, $listAllProcessNodes);

        // Добавить недостающие поля из AxlPhones в зарегистрированные телефоны из RisPhones
        foreach ($registeredPhones as $phone) {
            $axlPhone = $axlPhones->findByAttributes(['device' => $phone->name]);

            if (!is_null($axlPhone)) {
                $phone->fill([
                    'css' => trim($axlPhone->css),
                    'devicePool' => trim($axlPhone->dpool),
                    'prefix' => trim($axlPhone->prefix),
                    'phoneDN' => trim($axlPhone->dnorpattern),
                    'alertingName' => trim($axlPhone->fio),
                    'partition' => trim($axlPhone->pt),
                    'model' => trim($axlPhone->type),
                ]);
            } else {
                $logger->info('PHONE: ' . '[name]=' . $phone->name . ' [ip]=' . $phone->ipAddress . ' [publisher]=' . $cucmIp . ' [message]=It is not found in AXL on the publisher');
            }
        }

        // Опросить кажждый телефон по его IP через WEB Interface
        foreach ($registeredPhones as $phone) {
            // ------------------- DeviceInformationX -------------------------------------
            if (false === $phone->getDataFromWebDevInfo()) {
                $logger->info('PHONE: ' . '[name]=' . $phone->name . ' [ip]=' . $phone->ipAddress . ' [publisher]=' . $cucmIp . ' [message]=It does not have web access');
                continue;
            }
            // ------------------- NetworkConfigurationX -------------------------------------
            if (false === $phone->getDataFromWebNetConf()) {
                $logger->info('PHONE: ' . '[model]=' . $phone->model . ' [ip]=' . $phone->ipAddress . ' [publisher]=' . $cucmIp . ' [message]=It does not have web access by HTML for NetworkConfiguration');
            }
            // ------------------- PortInformationX -------------------------------------
            if (false === $phone->getDataFromWebPortInfo()) {
                $logger->info('PHONE: ' . '[model]=' . $phone->model . ' [ip]=' . $phone->ipAddress . ' [publisher]=' . $cucmIp . ' [message]=It does not have web access by HTML for PortInformation');
            }
        }

        // Возвращать только те телефоны по которым были ответы от AXL, RIS, DeviceInformationX
        return $registeredPhones->filter(
            function ($phone) {
                return isset($phone->versionID) && isset($phone->model) && isset($phone->ipAddress);
            }
        );
    }


    /**
     * @return $this|bool
     */
    protected function getDataFromWebDevInfo()
    {
        if (isset($this->ipAddress)) {
            $phoneData = simplexml_load_file('http://' . $this->ipAddress . '/DeviceInformationX');

            if (false === $phoneData) {
                return false;
            }

            $this->fill([
                'macAddress' => trim($phoneData->MACAddress->__toString()),
                'serialNumber' => trim($phoneData->serialNumber->__toString()),
                'modelNumber' => trim($phoneData->modelNumber->__toString()),
                'versionID' => trim($phoneData->versionID->__toString()),
                'appLoadID' => trim($phoneData->appLoadID->__toString()),
                'timezone' => trim($phoneData->timezone->__toString()),
            ]);
        }

        return $this;
    }


    /**
     * @return $this|bool
     */
    protected function getDataFromWebNetConf()
    {
        if (isset($this->ipAddress) && isset($this->model)) {

            // Чтение XML
            $phoneData = simplexml_load_file('http://' . $this->ipAddress . '/NetworkConfigurationX');
            if (false !== $phoneData) {
                $this->fill([
                    'dhcpEnabled' => trim($phoneData->DHCPEnabled->__toString()),
                    'dhcpServer' => trim($phoneData->DHCPServer->__toString()),
                    'domainName' => trim($phoneData->DomainName->__toString()),
                    'subNetMask' => trim($phoneData->SubNetMask->__toString()),
                    'tftpServer1' => trim($phoneData->TFTPServer1->__toString()),
                    'tftpServer2' => trim($phoneData->TFTPServer2->__toString()),
                    'defaultRouter' => trim($phoneData->DefaultRouter1->__toString()),
                    'dnsServer1' => trim($phoneData->DNSServer1->__toString()),
                    'dnsServer2' => trim($phoneData->DNSServer2->__toString()),
                    'callManager1' => trim($phoneData->CallManager1->__toString()),
                    'callManager2' => trim($phoneData->CallManager2->__toString()),
                    'callManager3' => trim($phoneData->CallManager3->__toString()),
                    'callManager4' => trim($phoneData->CallManager4->__toString()),
                    'vlanId' => trim($phoneData->VLANId->__toString()),
                    'userLocale' => trim($phoneData->UserLocale->__toString()),
                ]);
            } else {
                // Чтение HTML
                $dom = HtmlDomParser::file_get_html('http://' . $this->ipAddress . '/NetworkConfiguration');
                if (false !== $dom) {
                    // Define the phone's environment
                    preg_match('~\d+~', $this->model, $matches);
                    switch ($matches[0]) {
                        case '7912':
                            $rows = $dom->find('form table tr');
                            $item = 1;
                            break;
                        case '7905':
                            $rows = $dom->find('form table tr');
                            $item = 1;
                            break;
                        case '6921':
                            $rows = $dom->find('table tr');
                            $item = 2;
                            break;
                        default:
                            $rows = [];
                    }

                    $phoneFields = [
                        'dhcpenabled' => 'dhcpEnabled',
                        'dhcpвключен' => 'dhcpEnabled',
                        'dhcpserver' => 'dhcpServer',
                        'domainname' => 'domainName',
                        'tftpserver1' => 'tftpServer1',
                        'tftpserver2' => 'tftpServer2',
                        'defaultrouter' => 'defaultRouter',
                        'defaultrouter1' => 'defaultRouter',
                        'dnsserver1' => 'dnsServer1',
                        'dnsserver2' => 'dnsServer2',
                        'callmanager1' => 'callManager1',
                        'unifiedcm1' => 'callManager1',
                        'callmanager2' => 'callManager2',
                        'callmanager3' => 'callManager3',
                        'callmanager4' => 'callManager4',
                        'operationalvlanid' => 'vlanId',
                        'действующийкодvlan' => 'vlanId',
                        'userlocale' => 'userLocale',
                        'subnetmask' => 'subNetMask',
                        'маскаподсети' => 'subNetMask',
                    ];

                    foreach ($rows as $row) {
                        $field = $phoneFields[mb_ereg_replace(' ', '', mb_strtolower($row->find('td', 0)->text()))];
                        if (!is_null($field)) {
                            $this->fill([$field => trim($row->find('td', $item)->text())]);
                        }
                    }

                } else {
                    return false;
                }
            }
        }

        return $this;
    }


    /**
     * @return $this|bool
     */
    protected function getDataFromWebPortInfo()
    {
        if (isset($this->ipAddress) && isset($this->model)) {
            // Чтение XML
            $phoneData = simplexml_load_file('http://' . $this->ipAddress . '/PortInformationX?1');
            if (false !== $phoneData) {
                preg_match('~\d+~', $this->model, $matches);
                switch ($matches[0]) {
                    case '7940':
                        $this->fill([
                            'cdpNeighborDeviceId' => trim($phoneData->deviceId->__toString()),
                            'cdpNeighborIP' => trim($phoneData->ipAddress->__toString()),
                            'cdpNeighborPort' => trim($phoneData->port->__toString()),
                        ]);
                        break;
                    default:
                        $this->fill([
                            'cdpNeighborDeviceId' => trim($phoneData->CDPNeighborDeviceId->__toString()),
                            'cdpNeighborIP' => trim($phoneData->CDPNeighborIP->__toString()),
                            'cdpNeighborPort' => trim($phoneData->CDPNeighborPort->__toString()),
                        ]);
                }


            } else {
                // Чтение HTML
                $dom = HtmlDomParser::file_get_html('http://' . $this->ipAddress . '/PortInformation?1');
                if (false !== $dom) {
                    // Define the phone's environment
                    preg_match('~\d+~', $this->model, $matches);
                    switch ($matches[0]) {
                        case '6921':
                            $rows = $dom->find('table tr');
                            $item = 2;
                            break;
                        default:
                            $rows = [];
                    }

                    $phoneFields = [
                        'идентустройствасоседа' => 'cdpNeighborDeviceId',
                        'ipадрессоседа' => 'cdpNeighborIP',
                        'портсоседа' => 'cdpNeighborPort',
                    ];

                    foreach ($rows as $row) {
                        $field = $phoneFields[mb_ereg_replace('[ -]', '', mb_strtolower($row->find('td', 0)->text()))];
                        if (!is_null($field)) {
                            $this->fill([$field => trim($row->find('td', $item)->text())]);
                        }
                    }

                } else {
                    return false;
                }
            }
        }

        return $this;
    }



    /**
     * Получить данные по зарегистрированным телефонам из cucm используя RisPort
     *
     * @param string $ip
     * @param Collection $phones
     * @param Collection $nodes
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
                                        'cmName' => trim($node->cmNodeName),
                                        'cmIpAddress' => trim($node->cmNodeIpAddress),
                                        'name' => trim($cmDevice->Name),
                                        'ipAddress' => trim($cmDevice->IpAddress),
                                        'description' => trim($cmDevice->Description),
                                        'status' => trim($cmDevice->Status),
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
                                'cmName' => trim($node->cmNodeName),
                                'cmIpAddress' => trim($node->cmNodeIpAddress),
                                'name' => trim($cmDevice->Name),
                                'ipAddress' => trim($cmDevice->IpAddress),
                                'description' => trim($cmDevice->Description),
                                'status' => trim($cmDevice->Status),
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
                'subNetMask' => $this->subNetMask,
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
            $this->dhcpEnabled = mb_strtolower($this->dhcpEnabled);
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
                'timezone' => $this->timezone,
                'domainName' => ('Нет' == $this->domainName) ? null : $this->domainName,
                'dhcpEnabled' => (('yes' == $this->dhcpEnabled || 1 == $this->dhcpEnabled || 'да' == $this->dhcpEnabled) ? true : false),
                'dhcpServer' => (false === ($dhcpIp = (new IpTools(($this->dhcpServer) ?? ''))->address)) ? null : $dhcpIp,
                'tftpServer1' => (false === ($tftp1Ip = (new IpTools(($this->tftpServer1) ?? ''))->address)) ? null : $tftp1Ip,
                'tftpServer2' => (false === ($tftp2Ip = (new IpTools(($this->tftpServer2) ?? ''))->address)) ? null : $tftp2Ip,
                'defaultRouter' => (false === ($routerIp = (new IpTools(($this->defaultRouter) ?? ''))->address)) ? null : $routerIp,
                'dnsServer1' => (false === ($dns1Ip = (new IpTools(($this->dnsServer1) ?? ''))->address)) ? null : $dns1Ip,
                'dnsServer2' => (false === ($dns2Ip = (new IpTools(($this->dnsServer2) ?? ''))->address)) ? null : $dns2Ip,
                'callManager1' => preg_replace('~[ ]+~', ' ', $this->callManager1),
                'callManager2' => preg_replace('~[ ]+~', ' ', $this->callManager2),
                'callManager3' => preg_replace('~[ ]+~', ' ', $this->callManager3),
                'callManager4' => preg_replace('~[ ]+~', ' ', $this->callManager4),
                'vlanId' => $this->vlanId,
                'userLocale' => $this->userLocale,
                'cdpNeighborDeviceId' => $this->cdpNeighborDeviceId,
                'cdpNeighborIP' => (false === ($neighborIp = (new IpTools(($this->cdpNeighborIP) ?? ''))->address)) ? null : $neighborIp,
                'cdpNeighborPort' => $this->cdpNeighborPort,
            ])->save();

            $this->debugLogger->info('process: ' . '[name]=' . $this->ipAddress . '; [phoneInfo]= OK');
            $this->debugLogger->info('END: ' . '[name]=' . $this->ipAddress);

            return $this;
        } else {
            return false;
        }
    }
}
