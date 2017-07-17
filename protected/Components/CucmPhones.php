<?php
namespace App\Components;

use T4\Core\Collection;
use T4\Core\Exception;
use T4\Core\Std;

class CucmPhones extends Std
{
    protected $axlClient;
    protected $risPortClient;
    protected $publisherIP;

    public function __construct($ip)
    {
        // Common client's options
        $publisherIP = (new IpTools($ip))->address;
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'ciphers' => 'HIGH',
            ]
        ]);
        $username = $this->app->config->axl->username;
        $password = $this->app->config->axl->password;
        $schema = 'sch7_1';

        // AXL client
        $this->axlClient = new \SoapClient(realpath(ROOT_PATH . '/AXLscheme/' . $schema . '/AXLAPI.wsdl'), [
            'trace' => true,
            'exception' => true,
            'location' => 'https://' . $publisherIP . ':8443/axl',
            'login' => $username,
            'password' => $password,
            'stream_context' => $context,
        ]);

        // RisPort client
        $this->risPortClient = new \SoapClient('https://' . $publisherIP . ':8443/realtimeservice/services/RisPort?wsdl', [
            'trace' => true,
            'exception' => true,
            'location' => 'https://' . $publisherIP . ':8443/realtimeservice/services/RisPort',
            'login' => $username,
            'password' => $password,
            'stream_context' => $context,
        ]);

        $this->publisherIP = $publisherIP;
    }

    /**
     * @return Collection
     * @throws Exception
     */
    public function run()
    {
        // ---------- AXL ------------------------------------------------------
        // Get all CmNodes from the publisher
        $listCmNodes = new Collection();
        $cms = ($this->axlClient->ListAllProcessNodes())->return->processNode;
        foreach ($cms as $cm) {
            $listCmNodes->add((new Std())
                ->fill([
                    'cmNodeIpAddress' => $cm->name,
                    'cmNodeName' => $cm->description,
                ])
            );
        }

        // Get all phones from the publisher
        $phones = $this->axlClient->ExecuteSQLQuery(['sql' => '
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
        if (is_null($phones)) {
            throw new Exception('AXL:Publisher [' . $this->publisherIP . '] - Phones not found');
        }
        $allPhones = new Collection();
        foreach ($phones as $phone) {
            $allPhones->add($phone);
        }


        // ---------- RisPort ------------------------------------------------------
        // Poll the registered phones by risport service
        $n = 0;
        foreach ($allPhones as $phone) {
            $items['SelectItem[' . $n .']']['Item'] = $phone->device;
            $n++;
        }
        $registeredPhones = $this->risPortClient->SelectCmDevice('',[
            'MaxReturnedDevices' => 1000,
            'Class' => 'Phone',
            'Model' => 255,
            'Status' => 'Registered',
            'NodeName' => '',
            'SelectBy' => 'Name',
            'SelectItems' => $items,
        ]);

        if (0 === ($registeredPhones['SelectCmDeviceResult'])->TotalDevicesFound) {
            throw new Exception('RisPort:Publisher [' . $this->publisherIP . '] - Registered phones not found');
        }

        $cmNodes = ($registeredPhones['SelectCmDeviceResult'])->CmNodes;
        $registeredPhones = new Collection();
        foreach ($cmNodes as $cmNode) {
            if ('ok' == strtolower($cmNode->ReturnCode)) {
                $node = $listCmNodes->findByAttributes(['cmNodeIpAddress' => $cmNode->Name]);

                foreach ($cmNode->CmDevices as $cmDevice) {
                    $registeredPhones->add(
                        (new Std())->fill([
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

        // Добавим недостающие поля для RegisteredPhones из AllPhones
        foreach ($registeredPhones as $registeredPhone) {
            $phone = $allPhones->findByAttributes(['device' => $registeredPhone->name]);
            $registeredPhone->fill([
                'css' => $phone->css,
                'devicePool' => $phone->dpool,
                'prefix' => $phone->prefix,
                'phoneDN' => $phone->dnorpattern,
                'alertingName' => $phone->fio,
                'partition' => $phone->pt,
                'type' => $phone->type,
            ]);
        }

        // ---------- Web Interface - DeviceInformationX -----------------------
        // Опросить RegisteredPhones по их IpAddress через вэб интерфейс
        $polledPhones = new Collection();
        foreach ($registeredPhones as $phone) {
            $phoneData = simplexml_load_file('http://' . $phone->ipAddress . '/DeviceInformationX');
            if (false !== $phoneData) {
                $polledPhones->add($phoneData);
            } else {
                // Todo записать в лог о том, что у телефона нет вэб доступа
            }
        }

        // Добавим недостающие поля для RegisteredPhones из PolledPhones
        foreach ($registeredPhones as $registeredPhone) {
            $phone = $polledPhones->findByAttributes(['HostName' => $registeredPhone->name]);
            if (!is_null($phone)) {
                $registeredPhone->fill([
                    'macAddress' => $phone->MACAddress->__toString(),
                    'serialNumber' => $phone->serialNumber->__toString(),
                    'modelNumber' => $phone->modelNumber->__toString(),
                    'versionID' => $phone->versionID->__toString(),
                    'appLoadID' => $phone->appLoadID->__toString(),
                ]);
            }
        }

        return $registeredPhones;
    }
}
