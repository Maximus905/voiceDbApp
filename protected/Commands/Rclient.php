<?php

namespace App\Commands;

use T4\Console\Command;

class Rclient extends Command
{
    public function actionDefault()
    {
//        $url = "http://vm-utk-reg.rs.ru/rServer";
        $url = "http://voice.loc/rServer";

        $srcDir = realpath(ROOT_PATH . '/Tmp/Test_src');
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
//var_dump($result->errors);
            if (400 == $result->httpStatusCode){
                rename($filePath, $errDir . '\\' . $file);
            }

            if (202 == $result->httpStatusCode){
                rename($filePath, $okDir . '\\' . $file);
            }
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
        $url = "http://voice.loc/dataports";
//        $url = "http://voice.loc/rServer";
//        $url = "http://netcmdb-dev.rs.ru/rServer";

//        $srcDir = realpath(ROOT_PATH . '/Tmp/Test_src');
//        $srcDir = realpath(ROOT_PATH . '/Tmp/Test_err');
//        $srcDir = realpath(ROOT_PATH . '/Tmp/Test_dataset_1_errors');
        $srcDir = realpath(ROOT_PATH . '/Tmp/Test_dataset_2');

        $filePath = realpath($srcDir . '\\' . '10.100.240.1-32__2017-05-25__6-43-43.72926900.json');


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
}
