<?php

/**
 * Minimal daemon — writes a timestamp to a log file every 5 seconds.
 *
 * Start:      php examples/basic.php
 * Stop:       kill $(cat /tmp/my-daemon/pids/daemon-Cruide*)
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
    sleepSeconds: 5,
);

new class($config) extends AbstractDaemon implements DaemonInterface
{
    private string $logFile;

    public function onStart(): void
    {
        $this->logFile = $this->config->workDir . '/data.log';

        if (!is_dir(dirname($this->logFile))) {
            mkdir(dirname($this->logFile), 0755, true);
        }
    }

    public function process(): void
    {
        file_put_contents($this->logFile, date('c') . "\n", FILE_APPEND);
    }

    public function processException(\Throwable $e): void
    {
        error_log('[MyDaemon] ' . $e->getMessage());
    }

    public function onStop(): void
    {
        file_put_contents($this->logFile, "--- daemon stopped ---\n", FILE_APPEND);
    }

    public function reload(): void
    {
        file_put_contents($this->logFile, "--- config reloaded ---\n", FILE_APPEND);
    }
};

$daemon->run();