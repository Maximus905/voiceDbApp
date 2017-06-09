<?php

namespace App\Controllers;

use App\Models\DataPort;
use App\Models\DPortType;
use App\Models\Vrf;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use T4\Core\Collection;
use T4\Core\Exception;
use T4\Core\MultiException;
use T4\Core\Std;
use T4\Mvc\Controller;

class Dataports extends Controller
{
    public function actionDefault()
    {
        try {
            $logger = new Logger('DS-dataports');
            $logger->pushHandler(new StreamHandler(ROOT_PATH . '/Logs/surveyOfAppliances.log', Logger::DEBUG));

            $dataSet = (new Std())->fill(json_decode(file_get_contents('php://input')));

            // Check the validity of the input dataset
            if (0 == count($dataSet)) {
                throw new Exception('DATASET: Not a valid JSON format or empty an input dataset');
            }
            if (!isset($dataSet->dataSetType) || empty($dataSet->dataSetType)) {
                throw new Exception('DATASET: No field dataSetType or empty dataSetType');
            }
            if ('prefixes' != $dataSet->dataSetType) {
                throw new Exception('DATASET: Not a valid dataSetType');
            }
            if (!isset($dataSet->ip)) {
                throw new Exception('DATASET: Missing field ip');
            }
            if (!isset($dataSet->vrf_name)) {
                throw new Exception('DATASET: Missing field vrf_name');
            }
            if (!isset($dataSet->lotus_id)) {
                throw new Exception('DATASET: Missing field lotus_id');
            }
            if (!isset($dataSet->networks) || empty($dataSet->networks)) {
                throw new Exception('DATASET: Missing field networks or empty networks');
            }
            foreach ($dataSet->networks as $dataSetNetwork) {
                if (!isset($dataSetNetwork->interface)) {
                    throw new Exception('DATASET: Networks - Missing field interface');
                }
                if (!isset($dataSetNetwork->vrf_name)) {
                    throw new Exception('DATASET: Networks - Missing field vrf_name');
                }
                if (!isset($dataSetNetwork->mac)) {
                    throw new Exception('DATASET: Networks - Missing field mac');
                }
                if (!isset($dataSetNetwork->ip_address)) {
                    throw new Exception('DATASET: Networks - Missing field ip_address');
                }
                if (!isset($dataSetNetwork->vrf_rd)) {
                    throw new Exception('DATASET: Networks - Missing field vrf_rd');
                }
            }

            // Define VRF of the management IP
            if ('global' == mb_strtolower($dataSetNetwork->vrf_name)) {
                $vrf = Vrf::instanceGlobalVrf();
            }
            if ('global' != mb_strtolower($dataSetNetwork->vrf_name)) {
                $vrf = Vrf::findByColumn('name', $dataSet->vrf_name);
            }
            if (!($vrf instanceof Vrf)) {
                throw new Exception('Unknown VRF - ' . $dataSet->vrf_name);
            }

            // Find the dataport of the management IP by its VRF and IP
            $dataport = DataPort::findByIpVrf($dataSet->ip, $vrf);
            if (!($dataport instanceof DataPort)) {
                throw new Exception('The management IP '. $dataSet->ip .' does not exist in the database');
            }

            // Find the appliance which has the dataport
            $appliance = $dataport->appliance;

            $requestDataports = new Collection();
            // For each dataport in the request
            foreach ($dataSet->networks as $dataSetNetwork) {

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

            // Find the appliance's dataports which das not in the query, but there are in the database and output them to the log
            $appliance->refresh();
            foreach ($appliance->dataPorts as $dbDataport) {

                if (!$requestDataports->existsElement(['ipAddress' => $dbDataport->ipAddress])) {
                    $logger->warning('[host]=' . $appliance->details['hostname'] . ' [manageIP]=' . $dataSet->ip . ' ->> ' . $dbDataport->ipAddress . ' does not in the query, but there is in the database');
                }
            }

        } catch (MultiException $errs ) {
            foreach ($errs as $e) {
                $err['errors'][] = $e->getMessage();
                $logger->error('[host]=' . $appliance->details['hostname'] . ' [manageIP]=' . $dataSet->ip . ' ->> ' . $e->getMessage());
            }
        } catch (Exception $e) {
            $err['errors'][] = $e->getMessage();
            $logger->error('[host]=' . $appliance->details['hostname'] . ' [manageIP]=' . $dataSet->ip . ' ->> ' . $e->getMessage());
        }

        // Вернуть ответ
        $httpStatusCode = (isset($err)) ? 400 : 202;
        $response = (new Collection())
            ->merge(['ip' => $dataSet->ip])
            ->merge(['httpStatusCode' => $httpStatusCode])
            ->merge((400 == $httpStatusCode) ? $err : [] );
        echo(json_encode($response->toArray()));
        die;
    }
}
