<?php

require_once __DIR__ . '/../../protected/autoload.php';
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../protected/boot.php';
require_once __DIR__ . '/../DbTrait.php';
require_once __DIR__ . '/../EnvironmentTrait.php';

class VrfTest extends \PHPUnit\Framework\TestCase
{
    use DbTrait;
    use EnvironmentTrait;

    public function providerSanitizeVrf()
    {
        return [
            ['  test  ', 'test', '  1:1', '1:1', 'test', 'test'],
            ['  test  ', 'test', '  1.2.3.4   : 1  ', '1.2.3.4:1', 'test', 'test'],
        ];
    }

    public function providerValidVrf()
    {
        return [
            ['test 1', 'test 2', '1:1', '2:1', 'test'],
            ['test 2', 'test 3', '2:1', '1.1.1.1:1', 'test'],
            ['test 3', 'test 4', '1.1.1.1:1', '1.1.1.1:2', 'test'],
            ['test 4', 'test 1', '1.1.1.1:2', '1:1', 'test'],
        ];
    }

    public function providerInvalidVrf()
    {
        return [
            'invalidName_1' => [true, '1:1', 'test'],
            'invalidName_2' => [123, '1:1', 'test'],
            'invalidRd_1' => ['test', ':', 'test'],
            'invalidRd_2' => ['test', 1, 'test'],
            'invalidRd_3' => ['test', 'wrongRd', 'test'],
            'invalidRd_4' => ['test', '192.168.1.1', 'test'],
            'invalidRd_5' => ['test', '192.168.1.1:', 'test'],
            'invalidRd_6' => ['test', '1', 'test'],
            'invalidRd_7' => ['test', ':1', 'test']
        ];
    }

    /**
     * @param string $name
     * @param string $expectedName
     * @param string $rd
     * @param string $expectedRd
     * @param string $comment
     * @param string $expectedComment
     *
     * @dataProvider providerSanitizeVrf
     */
    public function testSanitizeVrf($name, $expectedName, $rd, $expectedRd, $comment, $expectedComment)
    {
        $vrf = (new \App\Models\Vrf())
            ->fill([
                'name' => $name,
                'rd' => $rd,
                'comment' => $comment
            ]);
        $this->assertEquals($expectedName, $vrf->name);
        $this->assertEquals($expectedRd, $vrf->rd);
        $this->assertEquals($expectedComment, $vrf->comment);
    }


    /**
     * @param $nameOld
     * @param $nameNew
     * @param $rdOld
     * @param $rdNew
     * @param $comment
     *
     * @dataProvider providerValidVrf
     */
    public function testValidVrf($nameOld, $nameNew, $rdOld, $rdNew, $comment)
    {
        $vrf = (new \App\Models\Vrf())
            ->fill([
                'name' => $nameOld,
                'rd' => $rdOld,
                'comment' => $comment
            ])
            ->save();
        $this->assertInstanceOf(\App\Models\Vrf::class, $vrf);
        $this->assertEquals($nameOld, $vrf->name);
        $this->assertEquals($rdOld, $vrf->rd);
        $this->assertEquals($comment, $vrf->comment);
    }

    /**
     * @param $nameOld
     * @param $nameNew
     * @param $rdOld
     * @param $rdNew
     * @param $comment
     *
     * @dataProvider providerValidVrf
     */
    public function testDoubleVrfError($nameOld, $nameNew, $rdOld, $rdNew, $comment)
    {
        $this->expectException(\T4\Core\Exception::class);
        (new \App\Models\Vrf())
            ->fill([
                'name' => $nameOld,
                'rd' => $rdOld,
                'comment' => $comment
            ])
            ->save();
    }

    /**
     * @param $nameOld
     * @param $nameNew
     * @param $rdOld
     * @param $rdNew
     * @param $comment
     *
     * @dataProvider providerValidVrf
     * @depends testValidVrf
     */
    public function testUpdateVrfError($nameOld, $nameNew, $rdOld, $rdNew, $comment)
    {
        $this->expectException(\T4\Core\Exception::class);
        $fromDb = \App\Models\Vrf::findByRd($rdOld);
        $fromDb
            ->fill([
                'name' => $nameNew,
            ])
            ->save();
        $fromDb
            ->fill([
                'rd' => $rdNew,
            ])
            ->save();
    }

    /**
     * @param $name
     * @param $rd
     * @param $comment
     *
     * @dataProvider providerInvalidVrf
     */
    public function testInvalidVrf($name, $rd, $comment)
    {
        $this->expectException(\T4\Core\Exception::class);
        (new \App\Models\Vrf())
            ->fill([
                'name' => $name,
                'rd' => $rd,
                'comment' => $comment
            ]);
    }

    public function testGlobalVrf()
    {
        $globalVrf = \App\Models\Vrf::instanceGlobalVrf();
        $this->assertInstanceOf(\App\Models\Vrf::class, $globalVrf);
        $this->assertEquals(\App\Models\Vrf::GLOBAL_VRF_NAME, $globalVrf->name);
        $this->assertEquals(\App\Models\Vrf::GLOBAL_VRF_RD, $globalVrf->rd);
        $this->assertEquals(\App\Models\Vrf::GLOBAL_VRF_COMMENT, $globalVrf->comment);
        $globalVrf_2 = \App\Models\Vrf::instanceGlobalVrf();
        $this->assertEquals($globalVrf->getPk(), $globalVrf_2->getPk());
    }
}