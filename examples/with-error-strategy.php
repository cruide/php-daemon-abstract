<?php

/**
 * Daemon that stops on the first error in process().
 *
 * ErrorStrategy::STOP — graceful shutdown with onStop() and PID file removal.
 * After three successful iterations an exception is thrown and the daemon exits.
 *
 * @author    Tischenko Alexander (http://alex-tisch.ru)
 * @package   cruide/php-daemon-abstract
 */

require __DIR__ . '/../vendor/autoload.php';

use Cruide\PHPDaemon\AbstractDaemon;
use Cruide\PHPDaemon\DaemonConfig;
use Cruide\PHPDaemon\ErrorStrategy;
use Cruide\PHPDaemon\DaemonInterface;

$config = new DaemonConfig(
    workDir: '/tmp/my-daemon',
    sleepSeconds: 2,
    errorStrategy: ErrorStrategy::STOP,
);

new class($config) extends AbstractDaemon implements DaemonInterface
{
    private int $attempt = 0;

    public function process(): void
    {
        $this->attempt++;

        if ($this->attempt > 3) {
            throw new \RuntimeException('Simulated failure on attempt #' . $this->attempt);
        }

        file_put_contents(
            $this->config->workDir . '/data.log',
            date('c') . " — attempt {$this->attempt}\n",
            FILE_APPEND
        );
    }

    public function processException(\Throwable $e): void
    {
        error_log('[ErrorStopDemo] ' . $e->getMessage());
    }

    public function onStart(): void {}
    public function onStop(): void {}
    public function reload(): void {}
};

$daemon->run();