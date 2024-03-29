<?php

namespace UnitTest\UnitTestClasses;


use T4\Console\Application;
use T4\Orm\Model;

class ModelClass_1 extends Model
{
    protected static $schema = [
        'table' => 'ModelClass_1',
        'columns' => [
            'columnOne' => ['type' => 'text'],
            'columnTwo' => ['type' => 'text'],
            'columnThree' => ['type' => 'text'],
            'columnFour' => ['type' => 'text'],
        ]
    ];

    public static function getDbDriver()
    {
        Application::instance()->setConfig(
            new \T4\Core\Config(ROOT_PATH . '/Tests/dbTestsConfig.php')
        );
        return parent::getDbDriver();
    }

}