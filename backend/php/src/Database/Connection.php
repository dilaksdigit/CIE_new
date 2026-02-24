<?php
namespace App\Database;

use Illuminate\Database\Capsule\Manager as Capsule;

class Connection {
    public static function connect() {
        $capsule = new Capsule;
        $capsule->addConnection([
            'driver' => env('DB_CONNECTION', 'mysql'),
            'host' => env('DB_HOST', 'localhost'),
            'database' => env('DB_DATABASE', 'cie_v232'),
            'username' => env('DB_USERNAME', 'cie_user'),
            'password' => env('DB_PASSWORD', 'your_secure_password'),
            'charset' => 'utf8',
            'collation' => 'utf8_unicode_ci',
            'prefix' => '',
        ]);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();
    }
}
