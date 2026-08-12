<?php

namespace Cruide\PHPDaemon;

/**
 * Error handling strategy for exceptions in process().
 *
 * Defines how the daemon behaves when an exception occurs in the main loop.
 *
 * @author    Tischenko Alexander (http://alex-tisch.ru)
 * @package   cruide/php-daemon-abstract
 */
final class ErrorStrategy
{
    /**
     * Continue running after an error.
     *
     * The daemon calls processException(), logs the error, and proceeds to the next iteration.
     */
    public const CONTINUE = 'continue';

    /**
     * Stop the daemon after an error.
     *
     * The daemon calls processException(), then onStop(), and exits.
     */
    public const STOP = 'stop';

    private function __construct()
    {
    }
}