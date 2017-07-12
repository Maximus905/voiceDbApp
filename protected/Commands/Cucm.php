<?php
namespace App\Commands;

use App\Components\CucmPhones;
use App\Components\DSPphone;
use App\Components\RLogger;
use App\Models\Appliance;
use T4\Console\Command;
use T4\Core\Exception;
use T4\Core\MultiException;

class Cucm extends Command
{
    const CUCMPUBLISHER = 'cmp';

    public function actionDefault()
    {
        $logger = RLogger::getInstance('Cucm');

        $cucmPublishers = Appliance::findAllByType(self::CUCMPUBLISHER);
        if (0 === $cucmPublishers->count()) {
            $logger->info('Devices with type [' . self::CUCMPUBLISHER . '] not found');
        }

        foreach ($cucmPublishers as $cucm) {
            $cucmIpAddress = $cucm->getManagementIp();

            try {
                $logger->info('START:[cucm]=' . $cucmIpAddress);

                $cucmPhones = (new CucmPhones($cucmIpAddress))->run();
                foreach ($cucmPhones as $phone) {
                    $result = (new DSPphone($phone))->run();
                    if (true !== $result) {
                        $logger->error('Data processing failed. [cucm]=' . $cucmIpAddress);
                    }
                }

                $logger->info('END:[cucm]=' . $cucmIpAddress);

            }catch (MultiException $errs) {
                foreach ($errs as $e) {
                    $logger->error('[cucm]=' . $cucmIpAddress . '; [message]=' . ($e->getMessage() ?? '""'));
                }
                echo 'CUCM[' . $cucmIpAddress .'] - ERRORS' . PHP_EOL;
            } catch (Exception $e) {
                $logger->error('[cucm]=' . $cucmIpAddress . '; [message]=' . ($e->getMessage() ?? '""'));
                echo 'CUCM[' . $cucmIpAddress .'] - ERROR' . PHP_EOL;
            } catch (\SoapFault $e) {
                $logger->error('[cucm]=' . $cucmIpAddress . '; [message]=' . ($e->getMessage() ?? '""'));
                echo 'CUCM[' . $cucmIpAddress .'] - ERROR' . PHP_EOL;
            }
        }

        echo 'OK' . PHP_EOL;
    }
}
