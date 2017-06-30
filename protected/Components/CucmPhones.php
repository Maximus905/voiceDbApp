<?php
namespace App\Components;

use T4\Core\Collection;
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
        $username = 'netcmdbAXL';
        $password = 'Dth.dAXL71';
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
     * @return bool
     */
    public function run()
    {
        // Get all phones with the 'Any' status
        $anyPhones = $this->axlClient->ExecuteSQLQuery(['sql' => '
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
//        var_dump($phones);


        // Poll the registered phones
        $n = 0;
        foreach ($anyPhones as $phone) {
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
        $cmNodes = ($registeredPhones['SelectCmDeviceResult'])->CmNodes;
//        var_dump($cmNodes);

        $registeredPhones = new Collection();
        foreach ($cmNodes as $cmNode) {
            if ('ok' == strtolower($cmNode->ReturnCode)) {
                foreach ($cmNode->CmDevices as $cmDevice) {
                    $phone = (new Std())->fill([
                        'cmName' => $cmNode->Name,
                        'Name' => $cmDevice->Name,
                        'IpAddress' => $cmDevice->IpAddress,
                        'DirNumber' => $cmDevice->DirNumber,
                        'Description' => $cmDevice->Description,
                        'TimeStamp' => $cmDevice->TimeStamp,
                        'Httpd' => $cmDevice->Httpd,
                    ]);
                    $registeredPhones->add($phone);
                }
            }
        }
//        var_dump($registeredPhones);


        foreach ($registeredPhones as $phone) {
// TODO сделать try catch для ""file_get_contents('http://' . $phone->IpAddress . '/DeviceInformationX')""
            if ('yes' == strtolower($phone->Httpd)) {
//                $data = new \SimpleXMLElement(file_get_contents('http://' . $phone->IpAddress . '/DeviceInformationX'));

                $data = file_get_contents('http://' . $phone->IpAddress . '/DeviceInformationX');
                var_dump($data);
            }
//            $phone->fill([
//                'MACAddress' => $data->MACAddress,
//                'phoneDN' => $data->phoneDN,
//                'appLoadID' => $data->appLoadID,
//                'versionID' => $data->versionID,
//                'serialNumber' => $data->serialNumber,
//                'modelNumber' => $data->modelNumber,
//            ]);
        }
//        var_dump($registeredPhones);



        return true;
    }
}
