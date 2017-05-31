<?php

namespace App\Components;

use T4\Core\Std;
use T4\Core\TSingleton;

class Timer extends Std
{
    use TSingleton;

    private $startTime;

    public function __construct($data = null)
    {
        $this->startTime = microtime(true);
        parent::__construct($data);
    }

    public function __set($key, $val)
    {
        $val = round($val, 3);
        parent::__set($key, $val);
    }

    public function start()
    {
        $this->startTime = microtime(true);
    }

    public function fix($name)
    {
        $this->$name = microtime(true) - $this->startTime;
    }
}