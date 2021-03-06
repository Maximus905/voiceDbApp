<?php
namespace App\Components\Cucm;

use App\Components\StreamLogger;
use App\Components\Swiitch\CiscoSwitch;
use App\Components\Swiitch\SwitchService;
use App\Models\Appliance;
use App\Models\Office;
use App\Models\PhoneInfo;

class CdpPhoneService
{
    private $logger;

    /**
     * PhoneService constructor.
     * @throws \Exception
     */
    public function __construct()
    {
        $this->logger = StreamLogger::instanceWith('PHONES_CDP_NEIGHBORS');
    }

    /**
     * @param string $sep
     * @return PhoneInfo|false
     */
    public function phoneWithSEP(string $sep)
    {
        $sepLength = 15;
        if (mb_strlen($sep) != $sepLength) {
            return PhoneInfo::findByColumn('name', $sep);
        }
        $macLength = 12;
        $mac = mb_substr($sep, -$macLength);
        return PhoneInfo::findByMac($mac);
    }

    /**
     * Updating data of phones connected to the polling switches
     */
    public function updateDataOfPhonesConnectedToPollingSwitches(): void
    {
        $switches = (new SwitchService())->switchesAvailableForPollingCdpNeighbors()->toArray();
        array_walk(
            $switches,
            function ($switch) {
                try {
                    $dataOfPhonesConnectedToSwitch = $this->dataOfPhonesConnectedToSwitch($switch);
                } catch (\Throwable $e) {
                    $this->logger->error('[message]=' . $e->getMessage() . ' [sw_id]=' . $switch->getPk());
                    return;
                }
                array_walk(
                    $dataOfPhonesConnectedToSwitch,
                    function ($dataOfPhone) use ($switch) {
                        try {
                            $phone = $this->phoneWithSEP($dataOfPhone['sep']);
                            if (false === $phone) {
                                throw new \Exception($dataOfPhone['sep'] . ' is unregistered phone');
                            }
                            $phone
                                ->updateCdpNeighborData(
                                    $dataOfPhone['sw_name'],
                                    $dataOfPhone['sw_ip'],
                                    $dataOfPhone['sw_port']
                                )
                                ->updateLocationByCdpNeighbor($switch);
                            if (!$phone->isCorrectConnectionPort($dataOfPhone['ph_port'])) {
                                throw new \Exception($dataOfPhone['sep'] . ' is connected on Port 2');
                            }
                        } catch (\Throwable $e) {
                            $this->logger->error(
                                '[message]=' . $e->getMessage() .
                                ' [sep]=' . $dataOfPhone['sep'] .
                                ' [sw_id]=' . $switch->getPk()
                            );
                        }
                    }
                );
            }
        );
    }

    /**
     * Data of unregistered phones connected in the office
     * @param Office $office
     * @param int $age hours
     * @return array
     */
    public function dataOfUnregisteredPhonesConnectedInOffice(Office $office, int $age)
    {
        $dataOfUnregisteredPhones = [];
        $dataOfPhonesConnectedInOffice = $this->dataOfPhonesConnectedInOffice($office);
        array_walk(
            $dataOfPhonesConnectedInOffice,
            function ($dataOfPhone) use (&$dataOfUnregisteredPhones, $age) {
                $phoneInfo = $this->phoneWithSEP($dataOfPhone['sep']);
                if (false == $phoneInfo) {
                    $dataOfPhone['model'] = '';
                    $dataOfPhone['inventory_number'] = '';
                    $dataOfPhone['last_update'] = '';
                    $dataOfPhone['cdp_last_update'] = '';
                    $dataOfPhone['is_in_db'] = false;
                    $dataOfUnregisteredPhones[] = $dataOfPhone;
                }
                if (false !== $phoneInfo && $phoneInfo->hoursSinceLastUpdate() > $age) {
                    $dataOfPhone['model'] = $phoneInfo->model ?? '';
                    $dataOfPhone['inventory_number'] = $phoneInfo->phone->inventoryNumber();
                    $dataOfPhone['last_update'] = $phoneInfo->phone->lastUpdate ?? '';
                    $dataOfPhone['cdp_last_update'] = $phoneInfo->cdpLastUpdate ?? '';
                    $dataOfPhone['is_in_db'] = true;
                    $dataOfUnregisteredPhones[] = $dataOfPhone;
                }
            }
        );
        return $dataOfUnregisteredPhones;
    }

    /**
     * Data of phones connected in the office
     * @param Office $office
     * @return array
     */
    public function dataOfPhonesConnectedInOffice(Office $office)
    {
        return $this->dataOfPhonesConnectedToSwitches(
            (new SwitchService())->pollingSwitchesInOffice($office)
        );
    }

    /**
     * Data of phones connected to the switches
     * @param array $switches
     * @return array
     */
    public function dataOfPhonesConnectedToSwitches(array $switches): array
    {
        $dataOfPhonesConnectedToSwitches = [];
        array_walk(
            $switches,
            function ($switch) use (&$dataOfPhonesConnectedToSwitches) {
                try {
                    $dataOfPhonesConnectedToSwitches = array_merge(
                        $dataOfPhonesConnectedToSwitches,
                        $this->dataOfPhonesConnectedToSwitch($switch)
                    );
                } catch (\Throwable $e) {
                    $this->logger->error('[message]=' . $e->getMessage() . ' [sw_id]=' . $switch->getPk());
                }
            }
        );
        return $dataOfPhonesConnectedToSwitches;
    }

    /**
     * Data of phones connected to the switch
     * @param Appliance $switch
     * @return array
     * @throws \Exception
     */
    public function dataOfPhonesConnectedToSwitch(Appliance $switch): array
    {
        $switch = new CiscoSwitch($switch);
        return array_map(
            function ($phone) use ($switch) {
                $phone['sw_name'] = $switch->hostname();
                $phone['sw_ip'] = $switch->managementIp();
                return $phone;
            },
            $switch->cdpPhoneNeighborsData()
        );
    }
}
