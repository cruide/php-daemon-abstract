<?php

namespace Cruide\PHPDaemon;

/**
 * Daemon configuration.
 *
 * DTO object passed to the AbstractDaemon constructor. All parameters have sensible defaults.
 *
 * @author    Tischenko Alexander (http://alex-tisch.ru)
 * @package   cruide/php-daemon-abstract
 */
final class DaemonConfig
{
    /** @var string Daemon working directory (contains pids/ and logs/ subdirectories) */
    public string $workDir;

    /** @var int Sleep between loop iterations in seconds */
    public int $sleepSeconds;

    /** @var int Maximum number of iterations (0 = unlimited) */
    public int $maxRepeats;

    /** @var int Maximum memory usage in MB (0 = unlimited) */
    public int $maxMemoryMb;

    /** @var string Error strategy: ErrorStrategy::CONTINUE or ErrorStrategy::STOP */
    public string $errorStrategy;

    /** @var int Log file size threshold for rotation in bytes (default 1 MB) */
    public int $logRotateBytes;

    /**
     * @param string $workDir        Daemon working directory
     * @param int    $sleepSeconds   Sleep between iterations (sec)
     * @param int    $maxRepeats     Iteration limit, 0 = unlimited
     * @param int    $maxMemoryMb    Memory limit in MB, 0 = unlimited
     * @param string $errorStrategy  ErrorStrategy::CONTINUE or ErrorStrategy::STOP
     * @param int    $logRotateBytes Log rotation threshold in bytes
     */
    public function __construct(
        string $workDir = '/phpd',
        int $sleepSeconds = 1,
        int $maxRepeats = 60,
        int $maxMemoryMb = 25,
        string $errorStrategy = ErrorStrategy::CONTINUE,
        int $logRotateBytes = 1_048_576,
    ) {
        $this->workDir = $workDir;
        $this->sleepSeconds = $sleepSeconds;
        $this->maxRepeats = $maxRepeats;
        $this->maxMemoryMb = $maxMemoryMb;
        $this->errorStrategy = $errorStrategy;
        $this->logRotateBytes = $logRotateBytes;
    }
}