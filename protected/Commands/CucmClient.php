<?php
namespace App\Commands;

use App\Components\RLogger;
use App\Models\Appliance;
use App\Models\Phone;
use T4\Console\Command;
use T4\Core\Exception;
use T4\Core\MultiException;

class CucmClient extends Command
{
    const PUBLISHER = 'cmp';

    public function actionDefault()
    {
        $logger = RLogger::getInstance('Cucm');

        foreach (Appliance::findAllByType(self::PUBLISHER) as $publisher) {
            $logger->info('START:[cucm]=' . $publisher->managementIp);
            try {

                $phones = Phone::getAllFromCucm($publisher->managementIp);
                foreach ($phones as $phone) {
                    $phone->save();
                }

            }catch (MultiException $errs) {
                foreach ($errs as $e) {
                    $logger->error('[cucm]=' . $publisher->managementIp . '; [message]=' . ($e->getMessage() ?? '""'));
                }
                echo 'CUCM[' . $publisher->managementIp .'] - ERRORS' . PHP_EOL;
            } catch (Exception $e) {
                $logger->error('[cucm]=' . $publisher->managementIp . '; [message]=' . ($e->getMessage() ?? '""'));
                echo 'CUCM[' . $publisher->managementIp .'] - ERROR' . PHP_EOL;
            } catch (\SoapFault $e) {
                $logger->error('[cucm]=' . $publisher->managementIp . '; [message]=' . ($e->getMessage() ?? '""'));
                echo 'CUCM[' . $publisher->managementIp .'] - ERROR' . PHP_EOL;
            }
            $logger->info('END:[cucm]=' . $publisher->managementIp);
        }

        echo 'OK' . PHP_EOL;
    }
}
