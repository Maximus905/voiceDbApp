<?php

namespace App\Commands;

use T4\Console\Command;
use T4\Core\Std;

class Rclient extends Command
{
    public function actionDefault()
    {
//        $url = "http://vm-utk-reg.rs.ru/rServer";
        $url = "http://voice.loc/rServer";

        $srcDir = realpath(ROOT_PATH . '/Tmp/Test_inventory');
//        $srcDir = realpath(ROOT_PATH . '/Tmp/Test_src');
        $okDir = realpath(ROOT_PATH . '/Tmp/Test_ok');
        $errDir = realpath(ROOT_PATH . '/Tmp/Test_err');

        $files = array_slice(scandir($srcDir), 2);

        $n = 1;
        foreach ($files as $file) {
            if ('.' == $file || '..' == $file) {
                continue;
            }

            $filePath = realpath($srcDir . '\\' . $file);
            $jsondata = file_get_contents($filePath);

            $curl = curl_init($url);
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($curl, CURLOPT_HEADER, false);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $jsondata);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            $result =  json_decode(curl_exec($curl));

            $result =  curl_exec($curl);
            var_dump($result);

//            $statusCode = (400 == $result->httpStatusCode) ? ' Bad Request' : ' Accepted';
//            echo ' request ' . $n++ . '  ->  ' . $result->httpStatusCode  . $statusCode . PHP_EOL;

            curl_close($curl);

//            if (400 == $result->httpStatusCode){
//                rename($filePath, $errDir . '\\' . $file);
//            }
//
//            if (202 == $result->httpStatusCode){
//                rename($filePath, $okDir . '\\' . $file);
//            }
        }
    }

    public function actionBack()
    {
        $srcDir = realpath(ROOT_PATH . '/Tmp/Test_src');
        $errDir = realpath(ROOT_PATH . '/Tmp/Test_err');
        $okDir = realpath(ROOT_PATH . '/Tmp/Test_ok');

        $files = scandir($errDir);
        foreach ($files as $file) {
            if ('.' == $file || '..' == $file) {
                continue;
            }
            $oldFileName = realpath($errDir . '\\' . $file);
            rename($oldFileName, $srcDir . '\\' . $file);
            echo $oldFileName . ' -> ' . realpath($srcDir . '\\' . $file) . PHP_EOL;
        }

        $files = scandir($okDir);
        foreach ($files as $file) {
            if ('.' == $file || '..' == $file) {
                continue;
            }
            $oldFileName = realpath($okDir . '\\' . $file);
            rename($oldFileName, $srcDir . '\\' . $file);
            echo $oldFileName . ' -> ' . realpath($srcDir . '\\' . $file) . PHP_EOL;
        }

//        $srcDir = realpath(ROOT_PATH . '/Tmp/Test_dataset_2');
//        $destinationDir = realpath(ROOT_PATH . '/Tmp/Test_dataset_1');
//
//        $files = scandir($srcDir);
//        foreach ($files as $file) {
//            if ('.' == $file || '..' == $file) {
//                continue;
//            }
//            $oldFileName = realpath($srcDir . '\\' . $file);
//            rename($oldFileName, $destinationDir . '\\' . $file);
//            echo $oldFileName . ' -> ' . realpath($destinationDir . '\\' . $file) . PHP_EOL;
//        }

        echo 'OK';
    }

    public function actionTestOne()
    {
//        $url = "http://voice.loc/dataports";
//        $url = "http://voice.loc/rServer";
        $url = "http://netcmdb-dev.rs.ru/rServer";


//        $srcDir = realpath(ROOT_PATH . '/Tmp/Test_src');
        $srcDir = realpath(ROOT_PATH . '/Tmp/Test_err');
//        $srcDir = realpath(ROOT_PATH . '/Tmp/Test_dataset_1_errors');
//        $srcDir = realpath(ROOT_PATH . '/Tmp/Test_inventory');

        $filePath = realpath($srcDir . '\\' . '10.101.6.135-32__2017-06-20__13-51-21.33845800.json');


        $jsondata = file_get_contents($filePath);
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $jsondata);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        $result =  curl_exec($curl);
        var_dump($result);

        curl_close($curl);
    }

    public function actionCount()
    {
//        $srcDir = realpath(ROOT_PATH . '/Tmp/Test_src');
        $srcDir = realpath(ROOT_PATH . '/Tmp/Test_inventory');

        $count = 0;
        $files = scandir($srcDir);
        foreach ($files as $file) {
            $count++;
        }

        echo $count . ' file';
    }

    public function actionFind()
    {
        $srcDir = realpath(ROOT_PATH . '/Tmp/Test_src');

        $files = scandir($srcDir);
        foreach ($files as $file) {
            if ('.' == $file || '..' == $file) {
                continue;
            }

            $filePath = realpath($srcDir . '\\' . $file);

            $jsondata = file_get_contents($filePath);
            $inputDataset = (new Std())->fill(json_decode($jsondata));
            $pattern = 'cluster';

            if ($pattern == $inputDataset->dataSetType) {
                echo $file . PHP_EOL;
            }
        }

        echo 'The end ...';
    }

    public function actionCountIP()
    {
        $file = realpath(ROOT_PATH . '/Logs/debug.log');
        $data = file($file);
        $start = 0;
        $finish = 0;
        foreach ($data as $str) {
            if (1 == preg_match('~RServer.INFO: START:~', $str)) {
//                echo $str;
                $start++;
            }
            if (1 == preg_match('~RServer.INFO: END:~', $str)) {
//                echo $str;
                $finish++;
            }
        }

        echo 'Starting -> ' . $start . ' requests' . PHP_EOL;
        echo 'Finished -> ' . $finish . ' requests' . PHP_EOL;
    }
}
