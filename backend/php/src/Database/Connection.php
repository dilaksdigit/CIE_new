<?php
namespace App\Database;

use Illuminate\Database\Capsule\Manager as Capsule;

class Connection {
    public static function connect() {
        $capsule = new Capsule;
        $capsule->addConnection([
            'driver' => env('DB_CONNECTION', 'pgsql'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'cie_v232'),
            'username' => env('DB_USERNAME', 'postgres'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8',
            'prefix' => '',
            'schema' => 'public',
            'sslmode' => env('DB_SSLMODE', 'prefer'),
            // SOURCE: CLAUDE.md §9 — timestamps must be UTC
            // Session-scoped; avoids unsafe global DB mutations.
            'timezone' => '+00:00',
        ]);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();
    }
}
