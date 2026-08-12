<?php

namespace Cruide\PHPDaemon;

use Cruide\PHPDaemon\Exception\DaemonException;

/**
 * Abstract daemon — base class for building PHP daemons.
 *
 * Handles all low-level mechanics: fork, detach from terminal, PID file management,
 * signal handling, log rotation, and STDIO redirection.
 *
 * Subclasses only need to implement the DaemonInterface methods.
 *
 * Usage:
 *   $config = new DaemonConfig(workDir: '/app');
 *   $daemon = new MyDaemon($config);
 *   $daemon->run();
 *
 * Signals:
 *   SIGTERM / SIGQUIT — graceful shutdown
 *   SIGHUP           — config reload via reload()
 *
 * @author    Tischenko Alexander (http://alex-tisch.ru)
 * @package   cruide/php-daemon-abstract
 */
abstract class AbstractDaemon implements DaemonInterface
{
    /** @var bool Stop flag */
    private bool $stopped = false;

    /** @var int Daemon start time (UNIX timestamp) */
    private int $startTime;

    /** @var int Current process PID */
    private int $pid;

    /** @var int Completed iteration counter */
    private int $repeats = 0;

    /** @var string Path to the PID file */
    private string $pidFile;

    /** @var string Path to the STDOUT log file */
    private string $outputLogFile;

    /** @var string Path to the STDERR log file */
    private string $errorsLogFile;

    /** @var string Path to the PHP errors log file */
    private string $phpErrorsLogFile;

    /**
     * Constructor.
     *
     * Forks the process, detaches from terminal, checks PID uniqueness,
     * registers signal handlers, rotates logs, and redirects I/O streams.
     *
     * @param DaemonConfig $config Daemon configuration
     * @throws DaemonException On fork failure, duplicate PID, or getmypid() failure
     */
    public function __construct(
        protected DaemonConfig $config,
    ) {
        $pid = pcntl_fork();

        if ($pid === -1) {
            throw new DaemonException('pcntl_fork() failed');
        }

        if ($pid > 0) {
            exit(0);
        }

        posix_setsid();

        $this->startTime = time();

        $pid = getmypid();
        if ($pid === false) {
            throw new DaemonException('getmypid() failed');
        }
        $this->pid = $pid;

        $className = static::class;
        $this->pidFile = $this->config->workDir . '/pids/daemon-' . $className . '.pid';
        $this->outputLogFile = $this->config->workDir . '/logs/' . $className . '-output.log';
        $this->errorsLogFile = $this->config->workDir . '/logs/' . $className . '-errors.log';
        $this->phpErrorsLogFile = $this->config->workDir . '/logs/' . $className . '-php-errors.log';

        $this->ensureUnique();

        pcntl_async_signals(true);

        pcntl_signal(SIGTERM, [$this, 'handleSignal']);
        pcntl_signal(SIGQUIT, [$this, 'handleSignal']);
        pcntl_signal(SIGHUP, [$this, 'handleSignal']);

        $this->rotateAllLogs();
        $this->redirectOutput();
        $this->writePidFile();

        echo "\n" . date('d.m.Y [H:i:s]') . " PHP daemon started\n";
    }

    /**
     * Start the daemon main loop.
     *
     * Execution order:
     *  1. onStart()  — initialization
     *  2. Main loop: process() → limit checks → sleep
     *  3. onStop()   — cleanup
     *  4. Remove PID file, print stats, exit()
     */
    public function run(): void
    {
        $this->onStart();

        while (!$this->stopped) {
            try {
                $this->process();
            } catch (\Throwable $e) {
                $this->processException($e);

                if ($this->config->errorStrategy === ErrorStrategy::STOP) {
                    $this->stopped = true;
                    break;
                }
            }

            $this->repeats++;

            if ($this->config->maxRepeats > 0 && $this->repeats >= $this->config->maxRepeats) {
                $this->stopped = true;
                break;
            }

            if ($this->config->maxMemoryMb > 0 && (memory_get_peak_usage(true) / 1048576) >= $this->config->maxMemoryMb) {
                $this->stopped = true;
                break;
            }

            if (!$this->stopped) {
                sleep($this->config->sleepSeconds);
            }
        }

        $this->onStop();
        $this->removePidFile();

        $uptime = $this->formatDuration(time() - $this->startTime);
        $memory = round(memory_get_peak_usage(true) / 1048576, 2);

        exit(
            date('d.m.Y [H:i:s]') . " PHP daemon stopped, uptime {$uptime}, memory {$memory}MB\n"
        );
    }

    /**
     * Signal handler.
     *
     * SIGTERM / SIGQUIT — set the stop flag.
     * SIGHUP           — call reload().
     *
     * @param int $signo Received signal number
     */
    public function handleSignal(int $signo): void
    {
        switch ($signo) {
            case SIGTERM:
            case SIGQUIT:
                $this->stopped = true;
                break;
            case SIGHUP:
                $this->reload();
                break;
        }
    }

    /**
     * Ensure only one instance of the daemon is running.
     *
     * If the PID file exists and the process is alive, throws DaemonException.
     * If the process is dead, removes the stale PID file.
     *
     * @throws DaemonException If a daemon instance is already running
     */
    private function ensureUnique(): void
    {
        $dir = dirname($this->pidFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        if (!is_file($this->pidFile)) {
            return;
        }

        $existingPid = (int) file_get_contents($this->pidFile);

        if ($existingPid <= 0) {
            return;
        }

        if (posix_kill($existingPid, 0)) {
            throw new DaemonException(
                "Daemon already running (PID: {$existingPid})"
            );
        }

        @unlink($this->pidFile);
    }

    /**
     * Write the PID file.
     *
     * Creates the pids/ directory if it does not exist.
     */
    private function writePidFile(): void
    {
        $dir = dirname($this->pidFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($this->pidFile, (string) $this->pid);
    }

    /**
     * Remove the PID file on daemon exit.
     */
    private function removePidFile(): void
    {
        if (is_file($this->pidFile)) {
            @unlink($this->pidFile);
        }
    }

    /**
     * Rotate all three log files on daemon startup.
     */
    private function rotateAllLogs(): void
    {
        $this->rotateLogFile($this->phpErrorsLogFile);
        $this->rotateLogFile($this->outputLogFile);
        $this->rotateLogFile($this->errorsLogFile);
    }

    /**
     * Rotate a single log file.
     *
     * If the file exceeds logRotateBytes, it is compressed into a gzip archive
     * with a date suffix and sequential number, then the original is deleted.
     *
     * @param string $logFile Path to the log file
     */
    private function rotateLogFile(string $logFile): void
    {
        if (!is_file($logFile)) {
            return;
        }

        if (filesize($logFile) <= $this->config->logRotateBytes) {
            return;
        }

        $j = 1;
        $date = date('Ymd');

        while (true) {
            $archiveFile = "{$logFile}-{$date}-{$j}.gz";

            if (!is_file($archiveFile)) {
                break;
            }

            $j++;
        }

        $this->compressFile($logFile, $archiveFile);
        @unlink($logFile);
    }

    /**
     * Compress a file into a gzip archive.
     *
     * @param string $src Source file path
     * @param string $dst Destination gzip file path
     */
    private function compressFile(string $src, string $dst): void
    {
        $content = file_get_contents($src);

        if ($content === false) {
            return;
        }

        $gz = gzopen($dst, 'w9');

        if ($gz === false) {
            return;
        }

        gzwrite($gz, $content);
        gzclose($gz);
    }

    /**
     * Redirect standard I/O streams.
     *
     * STDIN  → /dev/null
     * STDOUT → {workDir}/logs/{ClassName}-output.log
     * STDERR → {workDir}/logs/{ClassName}-errors.log
     *
     * PHP errors are directed to {workDir}/logs/{ClassName}-php-errors.log
     */
    private function redirectOutput(): void
    {
        global $STDIN, $STDOUT, $STDERR;

        ini_set('error_log', $this->phpErrorsLogFile);

        fclose($STDIN);
        fclose($STDOUT);
        fclose($STDERR);

        $logsDir = dirname($this->outputLogFile);
        if (!is_dir($logsDir)) {
            mkdir($logsDir, 0755, true);
        }

        $STDIN = fopen('/dev/null', 'r');
        $STDOUT = fopen($this->outputLogFile, 'ab');
        $STDERR = fopen($this->errorsLogFile, 'ab');
    }

    /**
     * Format a duration into a human-readable string.
     *
     * Example: 93784 → "1d 2:03:04"
     *
     * @param int $seconds Duration in seconds
     * @return string Formatted string
     */
    private function formatDuration(int $seconds): string
    {
        $days = intdiv($seconds, 86400);
        $seconds %= 86400;
        $hours = intdiv($seconds, 3600);
        $seconds %= 3600;
        $minutes = intdiv($seconds, 60);
        $seconds %= 60;

        $result = '';

        if ($days > 0) {
            $result .= "{$days}d ";
        }

        $result .= sprintf('%d:%02d:%02d', $hours, $minutes, $seconds);

        return $result;
    }
}