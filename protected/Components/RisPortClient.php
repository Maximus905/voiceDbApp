<?php
namespace App\Components;

use T4\Core\Std;
use T4\Mvc\Application;

class RisPortClient extends Std
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

        $this->connection = new \SoapClient('https://' . $ip . ':8443/realtimeservice/services/RisPort?wsdl', [
            'trace' => true,
            'exception' => true,
            'location' => 'https://' . $ip . ':8443/realtimeservice/services/RisPort',
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
