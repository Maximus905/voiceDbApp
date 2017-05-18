<?php

require_once __DIR__ . '/../../protected/autoload.php';
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../protected/boot.php';
require_once __DIR__ . '/../DbTrait.php';
require_once __DIR__ . '/../EnvironmentTrait.php';
require_once __DIR__ . '/DataSetProviderTrait.php';


class DataSetProcessorTest extends \PHPUnit\Framework\TestCase
{
    use DbTrait;
    use EnvironmentTrait;
    use DataSetProviderTrait;


    /**
     * @param $dataSet
     * @dataProvider providerInvalidApplianceDataSetError
     */
    public function testInvalidApplianceDataSetVerify($dataSet)
    {
        $dataSet = json_decode($dataSet);
        $method = new ReflectionMethod(\App\Components\DataSetProcessor::class, 'verifyApplianceDataSet');
        $method->setAccessible(true);
        try {
            $method->invoke(new \App\Components\DataSetProcessor($dataSet), $dataSet);
        } catch (\T4\Core\MultiException $errors) {
            $this->assertEquals(15, count($errors));
        }
    }

    /**
     * @param $dataSet
     * @dataProvider providerInvalidClusterDataSetError_1
     *
     * @expectedException \T4\Core\Exception
     * @expectedExceptionMessage DATASET: No field hostname for cluster
     */
    public function testInvalidClusterDataSetVerify_1($dataSet)
    {
        $dataSet = json_decode($dataSet);
        $method = new ReflectionMethod(\App\Components\DataSetProcessor::class, 'verifyClusterDataSet');
        $method->setAccessible(true);
        $method->invoke(new \App\Components\DataSetProcessor($dataSet), $dataSet);
    }

    /**
     * @param $dataSet
     * @dataProvider providerInvalidClusterDataSetError_2
     *
     * @expectedException \T4\Core\Exception
     * @expectedExceptionMessage DATASET: Empty clusterAppliances
     */
    public function testInvalidClusterDataSetVerify_2($dataSet)
    {
        $dataSet = json_decode($dataSet);
        $method = new ReflectionMethod(\App\Components\DataSetProcessor::class, 'verifyClusterDataSet');
        $method->setAccessible(true);
        $method->invoke(new \App\Components\DataSetProcessor($dataSet), $dataSet);
    }

    /**
     * @param $dataSet
     * @dataProvider providerInvalidClusterDataSetError_3
     */
    public function testInvalidClusterDataSetVerify_3($dataSet)
    {
        $dataSet = json_decode($dataSet);
        $method = new ReflectionMethod(\App\Components\DataSetProcessor::class, 'verifyClusterDataSet');
        $method->setAccessible(true);
        try {
            $method->invoke(new \App\Components\DataSetProcessor($dataSet), $dataSet);
        } catch (\T4\Core\MultiException $errors) {
            $this->assertEquals(12, count($errors));
        }
    }

    /**
     * @param $dataSet
     * @dataProvider providerValidApplianceDataSet
     */
    public function testValidApplianceDataSetVerify($dataSet)
    {
        $dataSet = json_decode($dataSet);
        $method = new ReflectionMethod(\App\Components\DataSetProcessor::class, 'verifyApplianceDataSet');
        $method->setAccessible(true);
        $this->assertTrue($method->invoke(new \App\Components\DataSetProcessor($dataSet), $dataSet));
    }

    /**
     * @param $dataSet
     * @dataProvider providerValidClusterDataSet
     */
    public function testValidClusterDataSetVerify($dataSet)
    {
        $dataSet = json_decode($dataSet);
        $method = new ReflectionMethod(\App\Components\DataSetProcessor::class, 'verifyClusterDataSet');
        $method->setAccessible(true);
        $this->assertTrue($method->invoke(new \App\Components\DataSetProcessor($dataSet), $dataSet));
    }

    /**
     * @param $dataSet
     * @dataProvider providerValidDataSet
     */
    public function testValidDataSetVerify($dataSet)
    {
        $dataSet = json_decode($dataSet);
        $method = new ReflectionMethod(\App\Components\DataSetProcessor::class, 'verifyDataSet');
        $method->setAccessible(true);
        $this->assertTrue($method->invoke(new \App\Components\DataSetProcessor($dataSet)));
    }

    /**
     * @param $dataSet
     * @dataProvider providerDetermineDeviceType_Appliance
     */
    public function testDetermineDeviceType_Appliance($dataSet)
    {
        $dataSet = json_decode($dataSet);
        $method = new ReflectionMethod(\App\Components\DataSetProcessor::class, 'determineDeviceType');
        $method->setAccessible(true);
        $this->assertEquals(\App\Components\DataSetProcessor::APPLIANCE, $method->invoke(new \App\Components\DataSetProcessor($dataSet)));
    }

    /**
     * @param $dataSet
     * @dataProvider providerDetermineDeviceType_Cluster
     */
    public function testDetermineDeviceType_Cluster($dataSet)
    {
        $dataSet = json_decode($dataSet);
        $method = new ReflectionMethod(\App\Components\DataSetProcessor::class, 'determineDeviceType');
        $method->setAccessible(true);
        $this->assertEquals(\App\Components\DataSetProcessor::CLUSTER, $method->invoke(new \App\Components\DataSetProcessor($dataSet)));
    }

}
