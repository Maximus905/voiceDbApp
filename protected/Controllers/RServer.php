<?php

namespace App\Controllers;

use App\Components\DataSetProcessor;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use T4\Core\Collection;
use T4\Core\Exception;
use T4\Core\MultiException;
use T4\Core\Std;
use T4\Mvc\Controller;

class RServer extends Controller
{
    /**
     * Warning: Используется только для ТЕСТОВ
     */
    public function actionTest()
    {
        $this->app->db->default = $this->app->db->phpUnitTest;
        $this->actionDefault();
    }

    public function actionDefault()
    {
        try {
            $rawInput = file_get_contents('php://input');
            $inputDataset = (new Std())->fill(json_decode($rawInput));

            if (0 == count($inputDataset)) {
                Logger::setTimezone(new \DateTimeZone('Europe/Moscow'));
                $logger = new Logger('DS-appliance');
                $logger->pushHandler(new StreamHandler(ROOT_PATH . '/Logs/surveyOfAppliances.log', Logger::DEBUG));

                throw new Exception('DATASET: Not a valid JSON format or empty an input dataset');
            }

            if ('appliance' == $inputDataset->dataSetType) {
                Logger::setTimezone(new \DateTimeZone('Europe/Moscow'));
                $logger = new Logger('DS-appliance');
                $logger->pushHandler(new StreamHandler(ROOT_PATH . '/Logs/surveyOfAppliances.log', Logger::DEBUG));
            }

            if ('cluster' == $inputDataset->dataSetType) {
                Logger::setTimezone(new \DateTimeZone('Europe/Moscow'));
                $logger = new Logger('DS-cluster');
                $logger->pushHandler(new StreamHandler(ROOT_PATH . '/Logs/surveyOfAppliances.log', Logger::DEBUG));
            }


            $result = (new DataSetProcessor($inputDataset))->run();
            if (false === $result) {
                throw new Exception('Dataset Processor: runtime error');
            }

        } catch (MultiException $errs) {
            foreach ($errs as $e) {
                $err['errors'][] = $e->getMessage();
                $logger->error('[host]=' . ($inputDataset->hostname ?? '""') . ' [manageIP]=' . ($inputDataset->ip ?? '""') . ' [message]=' . ($e->getMessage() ?? '""') . ' [dataset]=' . $rawInput);
            }
        } catch (Exception $e) {
            $err['errors'][] = $e->getMessage();
            $logger->error('[host]=' . ($inputDataset->hostname ?? '""') . ' [manageIP]=' . ($inputDataset->ip ?? '""') . ' [message]=' . ($e->getMessage() ?? '""') . ' [dataset]=' . $rawInput);
        }


        // Вернуть ответ
        $httpStatusCode = (isset($err)) ? 400 : 202;
        $response = (new Collection())
            ->merge(['ip' => $inputDataset->ip])
            ->merge(['httpStatusCode' => $httpStatusCode])
            ->merge((400 == $httpStatusCode) ? $err : [] );
        echo(json_encode($response->toArray()));
        die;
    }

    public function actionInfile()
    {
        $rawdata = file_get_contents('php://input');
        $inputDataset = json_decode(file_get_contents('php://input'));
        $ip = (isset($inputDataset->ip)) ? str_replace('/', '-', $inputDataset->ip) : '';

//        $cacheDir = realpath(ROOT_PATH . '/Tmp/Test_test/');
//        $cacheDir = realpath(ROOT_PATH . '/Tmp/Test_dataset_2/');
//        $cacheDir = realpath(ROOT_PATH . '/Tmp/Test_dataset_1/');
        $cacheDir = realpath(ROOT_PATH . '/Tmp/Test_dataset_1_errors/');
//        $cacheDir = realpath(ROOT_PATH . '/Tmp/Test_cache/');
//        $cacheDir = realpath(ROOT_PATH . '/Tmp/Test_src/');

        $mt = explode(' ', microtime());
        $rawmc = explode('.', $mt[0]);
        $mc = $rawmc[1];
        $datetime = date('Y-m-d__G-i-s.', $mt[1]);
        $fileName = $cacheDir . '\\' . $ip . '__' . $datetime . $mc . '.json';

        $file = fopen($fileName, 'w+');
        fwrite($file,$rawdata);
        fclose($file);
        die;
    }
}
