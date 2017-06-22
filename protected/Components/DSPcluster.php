<?php
namespace App\Components;

use App\Models\Cluster;
use T4\Core\Exception;
use T4\Core\MultiException;
use T4\Core\Std;


class DSPcluster extends Std
{
    protected $dataSet;
    protected $firstClusterAppliance = true;
    protected $debugLogger;


    /**
     * DSPcluster constructor.
     * @param null $dataSet
     */
    public function __construct($dataSet = null)
    {
        $this->dataSet = $dataSet;
        $this->debugLogger = RLogger::getInstance('DSPcluster', realpath(ROOT_PATH . '/Logs/debug.log'));
    }


    public function run()
    {
        $this->debugLogger->info('START: ' . '[ip]=' . $this->dataSet->ip);

        $this->verifyDataSet();

        $cluster = Cluster::findByTitle($this->dataSet->hostname);
        if (!($cluster instanceof Cluster)) {
            $cluster = (new Cluster())
                ->fill([
                    'title' => $this->dataSet->hostname
                ])
                ->save();
        }
        $this->debugLogger->info('process: ' . '[ip]=' . $this->dataSet->ip . '; [cluster]=' . $cluster->title);

        foreach ($this->dataSet->clusterAppliances as $dataSetClusterAppliance) {

            // IP address прописываем только у первого Appliance, входящего в состав кластера
            if (true !== $this->firstClusterAppliance) {
                $dataSetClusterAppliance->ip = '';
                (new DSPappliance($dataSetClusterAppliance, $cluster))->run();
            }

            if (true === $this->firstClusterAppliance) {
                $ap = (new DSPappliance($dataSetClusterAppliance, $cluster))->run();
                $this->firstClusterAppliance = false;
            }
        }

        $this->debugLogger->info('END: ' . '[ip]=' . $this->dataSet->ip);

        return true;
    }


    /**
     * @throws Exception|MultiException
     */
    protected function verifyDataSet()
    {
        if (0 == count($this->dataSet)) {
            throw new Exception('DATASET: Empty an input dataset');
        }

        if (!isset($this->dataSet->hostname)) {
            throw new Exception('DATASET: No field hostname for cluster');
        }
        if (!(new IpTools($this->dataSet->ip))->is_valid) {
            new Exception('DATASET(cluster): No field ip or not valid');
        }
        if (empty($this->dataSet->clusterAppliances)) {
            throw new Exception('DATASET: Empty clusterAppliances');
        }

        $errors = new MultiException();

        foreach ($this->dataSet->clusterAppliances as $dataSetAppliance) {

            if (!isset($dataSetAppliance->LotusId)) {
                $errors->add(new Exception('DATASET: No field LotusId'));
            }
            if (empty($dataSetAppliance->LotusId)) {
                $errors->add(new Exception('DATASET: Empty LotusId'));
            }
            if (!is_numeric($dataSetAppliance->LotusId)) {
                $errors->add(new Exception('DATASET: LotusId is not valid'));
            }
            if (!isset($dataSetAppliance->platformVendor)) {
                $errors->add(new Exception('DATASET: No field platformVendor'));
            }
            if (!isset($dataSetAppliance->platformSerial)) {
                $errors->add(new Exception('DATASET: No field platformSerial'));
            }
            if (!isset($dataSetAppliance->applianceType)) {
                $errors->add(new Exception('DATASET: No field applianceType'));
            }
            if (!isset($dataSetAppliance->applianceModules)) {
                $errors->add(new Exception('DATASET: No field applianceModules'));
            }
            if (!empty($dataSetAppliance->applianceModules)) {
                foreach ($dataSetAppliance->applianceModules as $moduleDataset) {

                    if (!isset($moduleDataset->product_number)) {
                        $errors->add(new Exception('DATASET: No field applianceModule->product_number'));
                    }
                    if (!isset($moduleDataset->serial)) {
                        $errors->add(new Exception('DATASET: No field applianceModule->serial'));
                    }
                    if (!isset($moduleDataset->description)) {
                        $errors->add(new Exception('DATASET: No field applianceModule->description'));
                    }
                    if (('' === $moduleDataset->serial) || ('' === $moduleDataset->product_number)) {
                        $errors->add(new Exception('DATASET: Empty applianceModule->serial or applianceModule->product_number'));
                    }
                }
            }
            if (!isset($dataSetAppliance->applianceSoft)) {
                $errors->add(new Exception('DATASET: No field applianceSoft'));
            }
            if (!isset($dataSetAppliance->softwareVersion)) {
                $errors->add(new Exception('DATASET: No field softwareVersion'));
            }
            if (!isset($dataSetAppliance->hostname)) {
                $errors->add(new Exception('DATASET: No field hostname'));
            }
            if (!(new IpTools($dataSetAppliance->ip))->is_valid) {
                $errors->add(new Exception('DATASET: No field ip or not valid'));
            }
            if (!isset($dataSetAppliance->chassis)) {
                $errors->add(new Exception('DATASET: No field chassis'));
            }
        }

        // Если DataSet не валидный, то заканчиваем работу
        if (0 < $errors->count()) {
            throw $errors;
        }
    }
}
