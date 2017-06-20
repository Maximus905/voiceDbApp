<?php
namespace App\Components;

use App\Models\DataPort;
use App\Models\DPortType;
use App\Models\Vrf;
use T4\Core\Collection;
use T4\Core\Exception;
use T4\Core\MultiException;
use T4\Core\Std;


class DSPprefixes extends Std
{
    const SLEEPTIME = 100000; // микросекунды
    const ITERATIONS = 520; // Колличество попыток получить доступ к db.lock файлу
    const DBLOCKFILE = ROOT_PATH_PROTECTED . '/db.lock';

    protected $dataSet;
    protected $dbLockFile;

    /**
     * DSPprefixes constructor.
     * @param null $dataSet
     */
    public function __construct($dataSet = null)
    {
        $this->dataSet = $dataSet;
    }


    public function run()
    {
        $this->verifyDataSet();

        try {
            // Заблокировать DB на запись
            if (false === $this->dbLock()) {
                throw new Exception('Can not get the lock file');
            }

            DataPort::getDbConnection()->beginTransaction();

            // Define VRF of the management IP
            if ('global' == mb_strtolower($this->dataSet->vrf_name)) {
                $vrf = Vrf::instanceGlobalVrf();
            }
            if ('global' != mb_strtolower($this->dataSet->vrf_name)) {
                $vrf = Vrf::findByColumn('name', $this->dataSet->vrf_name);
            }
            if (!($vrf instanceof Vrf)) {
                throw new Exception('Unknown VRF - ' . $this->dataSet->vrf_name);
            }

            // Find the dataport of the management IP by its VRF and IP
            $dataport = DataPort::findByIpVrf($this->dataSet->ip, $vrf);
            if (!($dataport instanceof DataPort)) {
                throw new Exception('The management IP '. $this->dataSet->ip .' does not exist in the database');
            }

            // Find the appliance which has the dataport
            $appliance = $dataport->appliance;


            $requestDataports = new Collection();
            // For each dataport in the request
            foreach ($this->dataSet->networks as $dataSetNetwork) {

                // Define VRF
                if ('global' == mb_strtolower($dataSetNetwork->vrf_name)) {
                    $vrf = Vrf::instanceGlobalVrf();
                }
                if ('global' != mb_strtolower($dataSetNetwork->vrf_name)) {
                    $vrf = Vrf::findByColumn('name', $dataSetNetwork->vrf_name);
                }
                if (!($vrf instanceof Vrf)) {
                    throw new Exception('Unknown VRF - ' . $dataSetNetwork->vrf_name . ' for ' . $dataSetNetwork->ip_address);
                }

                // Find the dataport by its VRF and IP
                $dataport = DataPort::findByIpVrf($dataSetNetwork->ip_address, $vrf);

                // If the dataport exist, then updated data on the dataport
                if ($dataport instanceof DataPort) {
                    $dataport->fill([
                        'appliance' => $appliance,
                        'vrf' => $vrf,
                        'ipAddress' => $dataSetNetwork->ip_address,
                        'macAddress' => $dataSetNetwork->mac,
                        'details' => [
                            'portName' => $dataSetNetwork->interface
                        ]
                    ])->save();
                }

                // If the dataport does not exist, then created the dataport
                if (!($dataport instanceof DataPort)) {

                    // Create the dataport
                    $dataport = (new DataPort())->fill([
                        'appliance' => $appliance,
                        'portType' => DPortType::findByType('Ethernet'),
                        'vrf' => $vrf,
                        'ipAddress' => $dataSetNetwork->ip_address,
                        'macAddress' => $dataSetNetwork->mac,
                        'isManagement' => false,
                        'details' => [
                            'portName' => $dataSetNetwork->interface
                        ]
                    ])->save();
                }

                // Add updated dataport to the requestDataports collection
                $requestDataports->add($dataport);
            }

            DataPort::getDbConnection()->commitTransaction();
            $this->dbUnLock();

        } catch (Exception $e) {
            DataPort::getDbConnection()->rollbackTransaction();
            throw new Exception($e->getMessage());
        }

        // Find the appliance's dataports which does not in the query, but there are in the database and output them to the log
        $appliance->refresh();
        $errs = new MultiException();
        foreach ($appliance->dataPorts as $dbDataport) {
            if (!$requestDataports->existsElement(['ipAddress' => $dbDataport->ipAddress])) {
                $errs->add($dbDataport->ipAddress . ' does not in the query, but there is in the database');
            }
        }
        if (0 < $errs->count()) {
            throw $errs;
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
     * @throws Exception
     * @throws MultiException
     */
    protected function verifyDataSet()
    {
        if (0 == count($this->dataSet)) {
            throw new Exception('DATASET: Empty an input dataset');
        }

        $errors = new MultiException();

        if (!isset($this->dataSet->ip)) {
            $errors->add('DATASET: Missing field ip');
        }
        if (!isset($this->dataSet->vrf_name)) {
            $errors->add('DATASET: Missing field vrf_name');
        }
        if (!isset($this->dataSet->lotus_id)) {
            $errors->add('DATASET: Missing field lotus_id');
        }
        if (!isset($this->dataSet->networks)) {
            $errors->add(new Exception('DATASET: Missing field networks'));
        }
        if (!empty($this->dataSet->networks)) {
            foreach ($this->dataSet->networks as $dataSetNetwork) {
                if (!isset($dataSetNetwork->interface)) {
                    $errors->add('DATASET: Networks - Missing field interface');
                }
                if (!isset($dataSetNetwork->vrf_name)) {
                    $errors->add('DATASET: Networks - Missing field vrf_name');
                }
                if (!isset($dataSetNetwork->mac)) {
                    $errors->add('DATASET: Networks - Missing field mac');
                }
                if (!isset($dataSetNetwork->ip_address)) {
                    $errors->add('DATASET: Networks - Missing field ip_address');
                }
                if (!isset($dataSetNetwork->vrf_rd)) {
                    $errors->add('DATASET: Networks - Missing field vrf_rd');
                }
            }
        }

        // Если DataSet не валидный, то заканчиваем работу
        if (0 < $errors->count()) {
            throw $errors;
        }
    }
}
