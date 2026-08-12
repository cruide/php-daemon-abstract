<?php

namespace Cruide\PHPDaemon;

/**
 * Contract for daemon classes.
 *
 * Every class extending AbstractDaemon must implement all methods of this interface.
 *
 * @author    Tischenko Alexander (http://alex-tisch.ru)
 * @package   cruide/php-daemon-abstract
 */
interface DaemonInterface
{
    /**
     * The daemon loop body — executed on every iteration.
     *
     * Place your business logic here: queue processing, sensor polling, etc.
     */
    public function process(): void;

    /**
     * Exception handler for errors thrown in process().
     *
     * Called before the daemon decides whether to continue or stop
     * (depending on ErrorStrategy). Use this to log the error,
     * send alerts, etc.
     */
    public function processException(\Throwable $e): void;

    /**
     * Called once before the main loop starts.
     *
     * Suitable for resource initialization, DB connection, environment checks.
     */
    public function onStart(): void;

    /**
     * Called once before the daemon exits.
     *
     * Release resources here: close connections, remove temp files, etc.
     */
    public function onStop(): void;

    /**
     * Called on SIGHUP signal.
     *
     * Use this to reload configuration without restarting the daemon.
     */
    public function reload(): void;
}