<?php
namespace App\Components;

use T4\Core\Std;
use T4\Mvc\Application;

class AxlClient extends Std
{
    protected $connection;

    public function __construct(string $ip)
    {
        $ip = (new IpTools($ip))->address;

        $axlConfig = (Application::instance())->config->axl;
        $username = $axlConfig->username;
        $password = $axlConfig->password;
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'ciphers' => 'HIGH',
            ]
        ]);
        // Todo - попробовать получить $schema из CUCM
        $schema = 'sch7_1';

        $this->connection = new \SoapClient(realpath(ROOT_PATH . '/AXLscheme/' . $schema . '/AXLAPI.wsdl'), [
            'trace' => true,
            'exception' => true,
            'location' => 'https://' . $ip . ':8443/axl',
            'login' => $username,
            'password' => $password,
            'stream_context' => $context,
        ]);
    }

    protected function getConnection()
    {
        return $this->connection;
    }
}
