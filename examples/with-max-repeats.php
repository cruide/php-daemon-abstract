<?php

/**
 * Daemon with a fixed number of iterations.
 *
 * maxRepeats = 5 — the daemon runs exactly 5 iterations and exits.
 * Useful for cron-like one-off tasks without an external scheduler.
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
    sleepSeconds: 1,
    maxRepeats: 5,
);

new class($config) extends AbstractDaemon implements DaemonInterface
{
    private int $iteration = 0;

    public function process(): void
    {
        $this->iteration++;
        file_put_contents(
            $this->config->workDir . '/data.log',
            date('c') . " — iteration {$this->iteration}\n",
            FILE_APPEND
        );
    }

    public function processException(\Throwable $e): void
    {
        error_log('[MaxRepeatsDemo] ' . $e->getMessage());
    }

    public function onStart(): void {}
    public function onStop(): void
    {
        file_put_contents(
            $this->config->workDir . '/data.log',
            "--- completed {$this->iteration} iterations, exiting ---\n",
            FILE_APPEND
        );
    }

    public function reload(): void {}
};

$daemon->run();