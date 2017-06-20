<?php
namespace App\Components;

use Monolog\Handler\StreamHandler;
use Monolog\Logger;


class RLogger
{
    protected static $instance;

    /**
     * RLogger constructor.
     * @param string $name
     * @param string $logfile
     */
    protected function __construct(string $name, string $logfile = ROOT_PATH . '/Logs/surveyOfAppliances.log')
    {
        Logger::setTimezone(new \DateTimeZone('Europe/Moscow'));
        static::$instance[$name] = new Logger($name);
        static::$instance[$name]->pushHandler(new StreamHandler($logfile, Logger::DEBUG));
    }

    /**
     * @param string $name
     * @return mixed
     */
    public static function getInstance(string $name)
    {
        if (null === static::$instance[$name]) {
            new static($name);
        }

        return static::$instance[$name];
    }
}
