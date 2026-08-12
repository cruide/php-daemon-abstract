<?php

namespace Cruide\PHPDaemon\Exception;

/**
 * Daemon exception.
 *
 * Thrown for critical errors: fork failure, duplicate daemon instance, etc.
 *
 * @author    Tischenko Alexander (http://alex-tisch.ru)
 * @package   cruide/php-daemon-abstract
 */
class DaemonException extends \RuntimeException
{
}