<?php
namespace App\Components;

use App\Exceptions\DblockException;
use App\Models\DataPort;
use App\Models\DPortType;
use App\Models\Vrf;
use T4\Core\Exception;
use T4\Core\MultiException;
use T4\Core\Std;

class DSPprefixes extends Std
{
    const DB_LOCK_FILE = ROOT_PATH_PROTECTED . '/db.lock';
    const SLEEP_TIME = 500; // микросекунды
    const ITERATIONS = 6000000; // Колличество попыток получить доступ к db.lock файлу

    private $dbLockFile;

    /**
     * @param Std $data
     * @return bool
     * @throws Exception
     * @throws MultiException
     */
    public function process(Std $data)
    {
        // At the moment, we do not support the uniqueness of VRF within only one device,
        // so we'll find the Appliance by the managementIP
        $managementDataPortIp = $data->ip;
        $managementDataPortVrf = Vrf::findByColumn('name', $data->vrf_name);
        $foundDataPort = DataPort::findByIpVrf($managementDataPortIp, $managementDataPortVrf);
        if (!is_null($foundDataPort) && $foundDataPort->isManagement) {
            $appliance = $foundDataPort->appliance;
        } else {
            throw new Exception('PREFIXES: [message]=Management dataport does not found; [managementIp]=' . $managementDataPortIp);
        }

        // Update or create the appliances's dataports
        $errors = new MultiException();
        foreach ($data->networks as $dataPortData) {
            try {
                $dataPortIp = (new IpTools($dataPortData->ip_address))->address;
                $dataPortMasklen = (new IpTools($dataPortData->ip_address))->masklen;
                if (!empty($dataPortData->vrf_rd)) {
                    $dataPortVrf = Vrf::findByColumn('rd', $dataPortData->vrf_rd);
                } else {
                    $dataPortVrf = Vrf::instanceGlobalVrf();
                }
                $foundDataPort = DataPort::findByIpVrf($dataPortIp, $dataPortVrf);
                $foundDataPortMac = mb_strtolower(preg_replace('~[:|\-|.]~','',$foundDataPort->macAddress));
                $dataPortDataMac = mb_strtolower(preg_replace('~[:|\-|.]~','',$dataPortData->mac));

                // Start transaction
                if (false === $this->dbLock()) {
                    throw new DblockException('PREFIXES: Can not get the lock file');
                }
                DataPort::getDbConnection()->beginTransaction();

                if (false != $foundDataPort) {
                    if ($foundDataPortMac == $dataPortDataMac) {
                        $dataPort = $foundDataPort;
                    } else {
                        $foundDataPort->delete();
                        $dataPort = new DataPort();
                    }
                } else {
                    $dataPort = new DataPort();
                }

                $dataPort->fill([
                    'appliance' => $appliance,
                    'portType' => DPortType::getEmpty(),
                    'macAddress' => implode(':', str_split($dataPortDataMac, 2)),
                    'ipAddress' => $dataPortIp,
                    'vrf' => $dataPortVrf,
                    'masklen' => $dataPortMasklen,
                    'isManagement' => ($dataPortIp == $managementDataPortIp) ? true : false,
                    'lastUpdate'=> (new \DateTime('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s P'),
                ]);
                if (!is_null($dataPort->details) && ($dataPort->details instanceof Std)) {
                    $dataPort->details->portName = $dataPortData->interface;
                    $dataPort->details->description = $dataPortData->description;
                } else {
                    $dataPort->details = new Std([
                        'portName' => $dataPortData->interface,
                        'description' => $dataPortData->description,
                    ]);
                }
                $dataPort->save();

                // End transaction
                DataPort::getDbConnection()->commitTransaction();
                $this->dbUnLock();
            } catch (\Throwable $e) {
                DataPort::getDbConnection()->rollbackTransaction();
                $this->dbUnLock();
                $errors->addException($e->getMessage());
            } catch (DblockException $e) {
                $errors->addException($e->getMessage());
            }
        }
        if (!$errors->isEmpty()) {
            throw $errors;
        }
        return true;
    }

    /**
     * Заблокировать db.lock файл
     *
     * @return bool
     * @throws Exception
     */
    protected function dbLock()
    {
        $this->dbLockFile = fopen(self::DB_LOCK_FILE, 'w');
        if (false === $this->dbLockFile) {
            throw new Exception('PHONE: Can not open the lock file');
        }
        $n = self::ITERATIONS;
        $blockedFile = flock($this->dbLockFile, LOCK_EX | LOCK_NB);
        while (false === $blockedFile && 0 !== $n--) {
            usleep(self::SLEEP_TIME);
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
