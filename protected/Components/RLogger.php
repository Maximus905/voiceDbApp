<?php
namespace App\Components;

use Monolog\Handler\StreamHandler;
use Monolog\Logger;


class RLogger
{
    const DEFAULTLOG = ROOT_PATH . '/Logs/surveyOfAppliances.log';
    protected static $instance;

    /**
     * RLogger constructor.
     * @param string $name
     * @param string $logfile
     */
    protected function __construct(string $name, string $logfile = null)
    {
        Logger::setTimezone(new \DateTimeZone('Europe/Moscow'));
        $logfile = $logfile ?? self::DEFAULTLOG;

        static::$instance[$name] = new Logger($name);
        static::$instance[$name]->pushHandler(new StreamHandler($logfile, Logger::DEBUG));
    }

    /**
     * @param string $name
     * @return mixed
     */
    public static function getInstance(string $name, string $logfile = null)
    {
        if (null === static::$instance[$name]) {
            new static($name, $logfile);
        }

        return static::$instance[$name];
    }
}
