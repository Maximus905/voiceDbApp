<?php

namespace App\Commands;

use App\Components\Connection\ConnectionImpl\SshConnectionHandler;
use App\Components\DSPphones;
use App\Components\Import\NeighborsImpl\PhonesCdpNeighborsFromSwitchesBySsh;
use App\Components\RLogger;
use App\Models\Appliance;
use App\Models\ApplianceType;
use App\Models\Office;
use App\Models\Phone;
use App\Models\PhoneInfo;
use App\ViewModels\DevModulePortGeo;
use T4\Console\Application;
use T4\Console\Command;
use T4\Core\MultiException;
use T4\Core\Std;
use T4\Dbal\Query;

class CucmsPhones extends Command
{
    const SSH_PORT = 22;
    const COMMAND_CDP_NEIGHBORS = 'show cdp neighbors';
    const MAX_LENGTH = -1;
    const FASTETHERNET_SHORT_NAME = 'FAS';
    const FASTETHERNET_FULL_NAME = 'FastEthernet';
    const GIGABITETHERNET_SHORT_NAME = 'GIG';
    const GIGABITETHERNET_FULL_NAME = 'GigabitEthernet';


    public function actionDefault()
    {
        $publishers = Appliance::findAllByType(ApplianceType::CUCM_PUBLISHER);
        foreach ($publishers as $publisher) {
            $publisherIp = $publisher->managementIp;
            if (false !== $publisherIp) {
                $logFile = ROOT_PATH . DS . 'Logs' . DS . 'phones_' . preg_replace('~\.~', '_', $publisherIp) . '.log';
                file_put_contents($logFile, '');
                $logger = RLogger::getInstance('CUCM-' . $publisherIp, $logFile);
                try {
                    $registeredPhonesData = Phone::findAllRegisteredIntoCucm($publisherIp);
                    foreach ($registeredPhonesData as $phoneData) {
                        try {
                            $data = (new Std())->fromArray($phoneData->getData());
                            (new DSPphones())->process($data);
                        } catch (\Throwable $e) {
                            $logger->error('UPDATE PHONE: [message]=' . ($e->getMessage() ?? '""') . '; [data]=' . json_encode($phoneData->getData()));
                        }
                    }
                    $this->writeLn('[publisher]=' . $publisherIp . '; Get phones - OK');
                } catch (MultiException $errs) {
                    foreach ($errs as $e) {
                        $logger->error('UPDATE PHONE: [message]=' . ($e->getMessage() ?? '""'));
                    }
                } catch (\Throwable $e) {
                    $logger->error('UPDATE PHONE: [message]=' . ($e->getMessage() ?? '""'));
                }
            }
        }
        $this->writeLn('Get phones from all cucms - OK');
    }

    public function actionGetFrom($publisherIp)
    {
        $logFile = ROOT_PATH . DS . 'Logs' . DS . 'phones_' . preg_replace('~\.~', '_', $publisherIp) . '.log';
        file_put_contents($logFile, '');
        $logger = RLogger::getInstance('CUCM-' . $publisherIp, $logFile);
        try {
            $registeredPhonesData = Phone::findAllRegisteredIntoCucm($publisherIp);
            foreach ($registeredPhonesData as $phoneData) {
                try {
                    $data = (new Std())->fromArray($phoneData->getData());
                    (new DSPphones())->process($data);
                } catch (\Throwable $e) {
                    $logger->error('UPDATE PHONE: [message]=' . ($e->getMessage() ?? '""') . '; [data]=' . json_encode($phoneData->getData()));
                }
            }
            $this->writeLn('[publisher]=' . $publisherIp . '; Get phones - OK');
        } catch (MultiException $errs) {
            foreach ($errs as $e) {
                $logger->error('UPDATE PHONE: [message]=' . ($e->getMessage() ?? '""'));
            }
        } catch (\Throwable $e) {
            $logger->error('UPDATE PHONE: [message]=' . ($e->getMessage() ?? '""'));
        }
    }

    public function actionGetNeighborsBySnmp()
    {
        $logFile = ROOT_PATH . DS . 'Logs' . DS . 'phones_neighbors.log';
        file_put_contents($logFile, '');
        $logger = RLogger::getInstance('PHONE', $logFile);

        $query = (new Query())
            ->select('"managementIp", hostname')
            ->from(DevModulePortGeo::getTableName())
            ->where('"appType" = :switch AND "managementIp" IS NOT NULL')
            ->params([
                ':switch' => 'switch',
            ])
        ;
        $switches = DevModulePortGeo::findAllByQuery($query);
        foreach ($switches as $switch) {
            $session = new \SNMP(\SNMP::VERSION_2c, $switch->managementIp, 'RegionRS2005');
            $neighbors = $session->walk('.1.3.6.1.4.1.9.9.23.1.2.1.1.6');
            $neighborsPort = $session->walk('.1.3.6.1.4.1.9.9.23.1.2.1.1.7');
            $neighborsInterface = $session->walk('.1.3.6.1.4.1.9.9.23.1.1.1.1.6');
            $session->close();

            foreach ($neighbors as $key => $neighbor) {
                if (1 == preg_match('~SEP.{12}~', $neighbor, $phoneName)) {
                    $phoneInfo = PhoneInfo::findByColumn('name', $phoneName[0]);
                    if (false !== $phoneInfo) {
                        $neighborPortKey = preg_replace('~9.9.23.1.2.1.1.6~', '9.9.23.1.1.1.1.6', $key);
                        $neighborPortKey = preg_replace('~\.\d+$~','', $neighborPortKey);
                        preg_match('~".+"+~', $neighborsInterface[$neighborPortKey], $cdpNeighborPort);

                        $cdpNeighborPort = str_replace('"', '', $cdpNeighborPort[0]);
                        $cdpNeighborDeviceId = $switch->hostname;
                        $cdpNeighborIP = $switch->managementIp;

                        if ($cdpNeighborDeviceId != $phoneInfo->cdpNeighborDeviceId || $cdpNeighborIP != $phoneInfo->cdpNeighborIP || $cdpNeighborPort != $phoneInfo->cdpNeighborPort) {
                            try {
                                $phoneInfo->fill([
                                    'cdpNeighborDeviceId' => $cdpNeighborDeviceId,
                                    'cdpNeighborIP' => $cdpNeighborIP,
                                    'cdpNeighborPort' => $cdpNeighborPort,
                                ]);
                                $phoneInfo->save();
                            } catch (MultiException $errs) {
                                foreach ($errs as $e) {
                                    $logger->error('UPDATE NEIGHBORS: [message]=' . ($e->getMessage() ?? '""'));
                                }
                            } catch (\Throwable $e) {
                                $logger->error('UPDATE NEIGHBORS: [message]=' . ($e->getMessage() ?? '""'));
                            }
                            $this->writeLn($phoneInfo->name . ': ' . $cdpNeighborDeviceId . ' - ' . $cdpNeighborIP . ' - ' . $cdpNeighborPort);
                        }

                        // Если порт подключения телефона не 'Port 1', сообщить об ошибке
                        $portKey = preg_replace('~9.9.23.1.2.1.1.6~', '9.9.23.1.2.1.1.7', $key);
                        preg_match('~\d+~', $neighborsPort[$portKey], $phonePort);
                        if (1 != (int)$phonePort[0]) {
                            $logger->error('UPDATE NEIGHBORS: [message]=Phone is connected by Port ' . (int)$phonePort[0] . '; [model]=' . $phoneInfo->model . '; [name]=' . $phoneInfo->name . '; [ip]=' . $phoneInfo->phone->dataPorts->first()->ipAddress . '; [number]=' . $phoneInfo->prefix . '-' . $phoneInfo->phoneDN . '; [office]=' . $phoneInfo->phone->location->title . '; [city]=' . $phoneInfo->phone->location->address->city->title . '; [address]=' . $phoneInfo->phone->location->address->address);
                        }
                    }
                }
            }
        }
        $this->writeLn('UPDATE NEIGHBORS - ok');
    }

    /**
     * Update phone CDP neighbors from switches by ssh
     *
     * @throws \Exception
     */
    public function actionUpdateCdpNeighborsFromSwitchesBySsh()
    {
        // Define logger
        $logFile = ROOT_PATH . DS . 'Logs' . DS . 'switchesNeighborsBySsh.log';
        file_put_contents($logFile, '');
        $logger = RLogger::getInstance('PHONE', $logFile);

        // Define Ssh connection handler
        $login = $this->app->config->ssh->login;
        $password = $this->app->config->ssh->password;
        $sshConnectionHandler = new SshConnectionHandler($login, $password);

        // Import Phone neighbors from switches by ssh
        (new PhonesCdpNeighborsFromSwitchesBySsh($sshConnectionHandler, $logger))->importNeighbors();

        $this->writeLn('UPDATE NEIGHBORS - ok');
    }

    /**
     * @param string $name
     * @throws \T4\Core\Exception
     */
    public function actionGetPhoneByName(string $name)
    {
        $childsPid = [];
        $query = (new Query())
            ->select('"managementIp"')
            ->from(DevModulePortGeo::getTableName())
            ->where('"appType" = :publisher AND "managementIp" IS NOT NULL')
            ->params([':publisher' => ApplianceType::CUCM_PUBLISHER]);
        $publishers = DevModulePortGeo::findAllByQuery($query);
        foreach ($publishers as $publisher) {
            // Branch the currently running process
            switch ($pid = pcntl_fork()) {
                case -1:
                    $this->writeln('Could not spawn child process');
                    break;
                case 0:
                    // Child process - workhorse
                    try {
                        $phone = Phone::findByNameIntoCucm($name, $publisher->managementIp);
                        if (false !== $phone) {
                            $this->writeLn(json_encode($phone->getData()));
                        }
                    } catch (\SoapFault $e) {
                        $this->writeLn(json_encode([
                            'error' => [$publisher->managementIp => $e->getMessage()],
                        ]));
                    }
                    exit();
                default:
                    // Keep the pid of child processes in the parent process
                    $childsPid[] = $pid;
            }
        }

        // Wait for all child processes to complete
        foreach ($childsPid as $childPid) {
            pcntl_waitpid($childPid, $status);
        }
        exit();
    }
}
