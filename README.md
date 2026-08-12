# cruide/php-daemon-abstract

Abstract PHP daemon base class for Unix/Linux. Handles forking, detaching, PID management,
signal handling, log rotation, and memory/repeat limits — so you only write the business logic.

## Requirements

- PHP >= 8.0
- Unix/Linux (fork, signals, process groups)

## PHP Extensions

This package requires extensions that are **not** part of the default PHP distribution.
They must be installed separately and are **available only on Unix/Linux**:

| Extension | Purpose                        | Install (Debian/Ubuntu)      |
|-----------|--------------------------------|------------------------------|
| `pcntl`   | fork, signals, async dispatch  | `apt install php-pcntl`      |
| `posix`   | setsid, process group, kill    | `apt install php-posix`      |
| `zlib`    | gzip log rotation              | usually bundled with PHP     |

Verify with:
```
php -m | grep -E 'pcntl|posix|zlib'
```

**pcntl** and **posix** will **never** work on Windows — the daemon is Unix/Linux only.

## Installation

```
composer require cruide/php-daemon-abstract
```

## Quick start

```php
use Cruide\PHPDaemon\AbstractDaemon;
use Cruide\PHPDaemon\DaemonConfig;
use Cruide\PHPDaemon\DaemonInterface;

$config = new DaemonConfig(workDir: '/var/app', sleepSeconds: 5);

$daemon = new class($config) extends AbstractDaemon implements DaemonInterface
{
    public function process(): void
    {
        file_put_contents(
            $this->config->workDir . '/data.log',
            date('c') . "\n",
            FILE_APPEND
        );
    }

    public function processException(\Throwable $e): void
    {
        error_log($e->getMessage());
    }

    public function onStart(): void {}
    public function onStop(): void {}
    public function reload(): void {}
};

$daemon->run();
```

Start: `php daemon.php` — the process forks to background.  
Stop: `kill $(cat /var/app/pids/daemon-*.pid)`

## DaemonConfig

All parameters have sensible defaults:

| Parameter        | Default                 | Description                                           |
|------------------|-------------------------|-------------------------------------------------------|
| `workDir`        | `/phpd`                 | Base directory for `pids/` and `logs/` subdirectories |
| `sleepSeconds`   | `1`                     | Sleep between loop iterations                         |
| `maxRepeats`     | `60`                    | Max loop iterations (0 = unlimited)                   |
| `maxMemoryMb`    | `25`                    | Max memory in MB before graceful stop (0 = unlimited) |
| `errorStrategy`  | `ErrorStrategy::CONTINUE` | What to do on exception in `process()`              |
| `logRotateBytes` | `1048576` (1 MB)        | File size threshold for log rotation                  |

```php
$config = new DaemonConfig(
    workDir: '/my-app',
    sleepSeconds: 10,
    maxRepeats: 0,          // run forever
    maxMemoryMb: 128,
    errorStrategy: ErrorStrategy::STOP,
);
```

## DaemonInterface

Your daemon class must implement these 5 methods:

| Method             | When it is called                                      |
|--------------------|--------------------------------------------------------|
| `process()`        | Every loop iteration — your business logic goes here   |
| `processException(Throwable)` | When `process()` throws — log, alert, etc.   |
| `onStart()`        | Once, before the main loop starts                      |
| `onStop()`         | Once, before the daemon exits (cleanup)                |
| `reload()`         | On `SIGHUP` — reload config or state without restart   |

## Error handling

Two strategies, configured via `DaemonConfig::$errorStrategy`:

- `ErrorStrategy::CONTINUE` (default) — log the exception, keep running
- `ErrorStrategy::STOP` — call `processException()`, then `onStop()`, then exit

## Signal handling

| Signal   | Action                                  |
|----------|-----------------------------------------|
| SIGTERM  | Graceful stop (onStop → remove PID → exit) |
| SIGQUIT  | Same as SIGTERM                         |
| SIGHUP   | Calls `reload()` without restarting     |

## Log management

- STDIN/STDOUT/STDERR are redirected to log files under `{workDir}/logs/`
- PHP errors go to `{workDir}/logs/{ClassName}-php-errors.log`
- On startup, log files larger than `logRotateBytes` are gzip-archived with date suffix

## PID file

- Written to `{workDir}/pids/daemon-{ClassName}.pid`
- Uniqueness check at startup: if a process with that PID is alive, `DaemonException` is thrown
- Automatically removed on clean exit

## More examples

See the `examples/` directory:

- `basic.php` — minimal daemon with all lifecycle methods
- `with-error-strategy.php` — stops on first error (ErrorStrategy::STOP)
- `with-reload.php` — reloads config on SIGHUP
- `with-max-repeats.php` — fixed number of iterations, then exits

## License

MIT — see [LICENSE](LICENSE).