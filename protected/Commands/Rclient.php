<?php

namespace App\Commands;

use T4\Console\Command;

class Rclient extends Command
{
    public function actionDefault()
    {
//        $url = "http://voice.loc/rserver/infile";
        $url = "http://voice.loc/rserver";
        $srcDir = realpath(ROOT_PATH . '/Tmp/Test_src');
        $errDir = realpath(ROOT_PATH . '/Tmp/Test_err');
        $okDir = realpath(ROOT_PATH . '/Tmp/Test_ok');

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

            $statusCode = (400 == $result->httpStatusCode) ? ' Bad Request' : ' Accepted';
            echo ' request ' . $n++ . '  ->  ' . $result->httpStatusCode  . $statusCode . PHP_EOL;

            curl_close($curl);

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

        echo 'OK';
    }

    public function actionTest()
    {
        $fp = fopen(ROOT_PATH_PROTECTED . '/db.lock', 'w');
        flock($fp, LOCK_EX);

//        $url = "http://10.99.120.170/rserver";
        $url = "http://voice.loc/rserver";
        $okDir = realpath(ROOT_PATH . '/Tmp/Test_ok');

        $filePath = realpath($okDir . '\\item_2017050511124264561700.json');
        $jsondata = file_get_contents($filePath);

        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $jsondata);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $result =  curl_exec($curl);
        curl_close($curl);

        var_dump($result);

        flock($fp, LOCK_UN);
        fclose($fp);


        die;
    }
}
