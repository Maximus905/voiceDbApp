<?php
/**
 * Created by PhpStorm.
 * User: rust
 * Date: 30.05.2017
 * Time: 17:39
 */

namespace App\Controllers;


use App\Components\Timer;
use App\Models\User;
use T4\Http\Request;

trait DebugTrait
{
    protected function access($action, $params = [])
    {
        $user = User::instance();
        if ('on' == (new Request())->get->debug) {
            $user->debugMode = true;
            $this->data->timer = Timer::instance();
        }
        $this->data->user = $user;
        return parent::access($action, $params);
    }
}