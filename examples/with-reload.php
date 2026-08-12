<?php

/**
 * Daemon that reloads its config on SIGHUP.
 *
 * Send kill -HUP <pid> to make the daemon re-read settings from config.txt.
 * Each iteration logs the current value of the "prefix" setting.
 *
 * @author    Tischenko Alexander (http://alex-tisch.ru)
 * @package   cruide/php-daemon-abstract
 */

require __DIR__ . '/../vendor/autoload.php';

use Cruide\PHPDaemon\AbstractDaemon;
use Cruide\PHPDaemon\DaemonConfig;
use Cruide\PHPDaemon\DaemonInterface;

$config = new DaemonConfig(
    workDir: '/tmp/my-daemon',
    sleepSeconds: 3,
);

new class($config) extends AbstractDaemon implements DaemonInterface
{
    private string $prefix = 'default';

    public function onStart(): void
    {
        $this->reload();
    }

    public function process(): void
    {
        file_put_contents(
            $this->config->workDir . '/data.log',
            date('c') . " — [{$this->prefix}] working\n",
            FILE_APPEND
        );
    }

    public function reload(): void
    {
        $file = $this->config->workDir . '/config.txt';

        if (is_file($file)) {
            $this->prefix = trim(file_get_contents($file)) ?: 'default';
        } else {
            $this->prefix = 'default';
        }

        file_put_contents(
            $this->config->workDir . '/data.log',
            date('c') . " — reload(): prefix = '{$this->prefix}'\n",
            FILE_APPEND
        );
    }

    public function processException(\Throwable $e): void
    {
        error_log('[ReloadDemo] ' . $e->getMessage());
    }

    public function onStop(): void {}
};

$daemon->run();