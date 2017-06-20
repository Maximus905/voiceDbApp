<?php
namespace App\Components;

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use T4\Core\Exception;
use T4\Core\Std;


class DSPerror extends Std
{
    protected $dataSet;

    /**
     * DSPerror constructor.
     * @param null $dataSet
     */
    public function __construct($dataSet = null)
    {
        $this->dataSet = $dataSet;
    }


    public function run()
    {
        $this->verifyDataSet();

        $logger = new Logger('DS-error');
        $logger->pushHandler(new StreamHandler(ROOT_PATH . '/Logs/surveyOfAppliances.log', Logger::DEBUG));
        $logger->error('[host]=' . ($this->dataSet->hostname ?? '""') . ' [manageIP]=' . ($this->dataSet->ip ?? '""') . ' [message]=' . ($this->dataSet->message ?? '""'));

        return true;
    }


    /**
     * @throws Exception
     */
    protected function verifyDataSet()
    {
        if (0 == count($this->dataSet)) {
            throw new Exception('DATASET: Empty an input dataset');
        }
        if (!isset($this->dataSet->ip)) {
            throw new Exception('DATASET: No field ip');
        }
        if (!isset($this->dataSet->hostname)) {
            throw new Exception('DATASET: No field hostname');
        }
        if (!isset($this->dataSet->message)) {
            throw new Exception('DATASET: No field message');
        }
    }
}
