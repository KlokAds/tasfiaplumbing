<?php

namespace Tests\Feature;

use Tests\TestCase;

class InstallKeyTest extends TestCase
{
    public function test_the_installer_needs_its_key_when_one_is_set(): void
    {
        $lock = storage_path('framework/installed.lock');
        $had = is_file($lock) ? file_get_contents($lock) : null;
        @unlink($lock);
        // A fresh upload: MySQL chosen but no database yet (a connection that fails at once, no network wait).
        $default = config('database.default');
        config(['app.install_key' => 'k3y-for-test', 'database.connections.mysql' => ['driver' => 'sqlite', 'database' => '', 'prefix' => ''], 'database.default' => 'mysql']);

        try {
            $this->get('/install')->assertNotFound();
            $this->get('/install?key=wrong')->assertNotFound();
            $this->post('/install', ['host' => 'evil.example.com', 'key' => 'wrong'])->assertNotFound();
            $this->get('/install?key=k3y-for-test')->assertOk();
        } finally {
            config(['database.default' => $default]);
            $had === null ? @unlink($lock) : file_put_contents($lock, $had);
        }
    }
}
