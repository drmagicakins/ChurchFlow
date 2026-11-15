<?php

namespace App\Console\Commands;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Foundation\Console\ServeCommand as BaseServeCommand;
use Symfony\Component\Process\Process;

/**
 * `php artisan serve`, with a writable upload temp directory.
 *
 * WHY THIS EXISTS. Laravel's ServeCommand builds the `php -S` child process in
 * startProcess() with a hand-picked environment: when an .env file is present,
 * every variable NOT on `$passthroughVariables` is passed as `false`, which
 * DELETES it from the child. `TEMP` and `TMP` are not on that list.
 *
 * On Windows, PHP's `sys_get_temp_dir()` then falls back to `C:\Windows`, which
 * is not writable, so PHP cannot create the temp file into which it spools every
 * upload BEFORE the request reaches Laravel. The observed result was
 *
 *     PHP Request Startup: File upload error - unable to create a temporary file
 *     $_FILES['photo']['error'] = 6   // UPLOAD_ERR_NO_TMP_DIR
 *
 * for EVERY photo, including ones of a few hundred KB — so it presented as a
 * file-size or file-type problem and was not. No application code can repair it
 * either: `upload_tmp_dir` is PHP_INI_SYSTEM, so `ini_set()` returns false and
 * the framework never receives a usable file.
 *
 * The repair therefore has to happen where the child is built — here. With
 * TEMP/TMP present, sys_get_temp_dir() is a real directory and PHP receives the
 * upload normally.
 *
 * Everything else is inherited: the options, host/port resolution, .env reload
 * handling, the worker logic and all output come from Laravel's class, which
 * this extends without changing.
 */
class ServeCommand extends BaseServeCommand
{
    /**
     * Start the server with TEMP/TMP/TMPDIR pointing at a writable directory.
     *
     * The body is Laravel's own, unchanged except for the extra three variables.
     * It is repeated rather than wrapped because the parent's method both builds
     * and STARTS the process; there is no seam to inject into without copying.
     */
    protected function startProcess($hasEnvironment)
    {
        $environment = (new Collection($_ENV))->mapWithKeys(function ($value, $key) use ($hasEnvironment) {
            if ($this->option('no-reload') || ! $hasEnvironment) {
                return [$key => $value];
            }

            return in_array($key, static::$passthroughVariables) ? [$key => $value] : [$key => false];
        })->merge(['PHP_CLI_SERVER_WORKERS' => $this->phpServerWorkers]);

        if ($tempDir = $this->usableTempDir()) {
            $environment = $environment->merge([
                // Which of the three PHP consults differs between Windows builds,
                // so all of them are set rather than guessing.
                'TEMP' => $tempDir,
                'TMP' => $tempDir,
                'TMPDIR' => $tempDir,
            ]);
        }

        $process = new Process($this->serverCommand(), public_path(), $environment->all());

        $this->trap(fn () => [SIGTERM, SIGINT, SIGHUP, SIGUSR1, SIGUSR2, SIGQUIT], function ($signal) use ($process) {
            if ($process->isRunning()) {
                $process->stop(10, $signal);
            }

            exit;
        });

        $process->start($this->handleProcessOutput());

        return $process;
    }

    /**
     * The `php -S` invocation, with the application's upload limits applied.
     *
     * The built-in server reads php.ini only — it ignores public/.user.ini an
     * FPM/CGI host would honour — so on a stock PHP 8 build it caps uploads at
     * the default upload_max_filesize of 2M. A development server that accepts
     * less than the screen promises is the same misconfiguration this class was
     * written to fix, so the two values are passed explicitly.
     *
     * Values come from public/.user.ini so there is ONE place describing the
     * limits; the parse is trivial (a handful of `name = value` lines).
     */
    protected function serverCommand()
    {
        $command = parent::serverCommand();

        foreach ($this->uploadIniSettings() as $setting) {
            $command = array_merge([$command[0]], ['-d', $setting], array_slice($command, 1));
        }

        return $command;
    }

    /**
     * Upload-relevant settings read from public/.user.ini, as `-d name=value`
     * pairs.
     *
     * Only these three are taken: they are PHP_INI_SYSTEM, so a -d at startup is
     * the only way the dev server can honour them. Reading them from the same
     * file keeps a single source of truth for the limits.
     */
    private function uploadIniSettings(): array
    {
        $file = public_path('.user.ini');

        if (! is_file($file)) {
            return [];
        }

        $wanted = ['upload_max_filesize', 'post_max_size', 'upload_tmp_dir'];
        $settings = [];

        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, ';') || ! str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = array_map('trim', explode('=', $line, 2));
            $key = strtolower($key);

            if (! in_array($key, $wanted, true) || $value === '') {
                continue;
            }

            // A relative upload_tmp_dir is resolved from public/, the directory
            // the built-in server runs in — resolve it here so the value PHP gets
            // is already absolute.
            if ($key === 'upload_tmp_dir' && ! Str::startsWith($value, ['/', '\\']) && ! preg_match('/^[A-Za-z]:/', $value)) {
                $value = public_path($value);
            }

            // % not a supported variable for ini_set, but -d accepts the raw
            // string; only a value containing shell-hostile characters is refused.
            if (preg_match('/["\r\n]/', $value)) {
                continue;
            }

            $settings[] = $key.'='.$value;
        }

        return $settings;
    }

    /**
     * A directory PHP can write upload temp files to, or null when none exists.
     *
     * `storage/app/upload-tmp` is preferred: it is inside the application, so it
     * is known to be writable (finished uploads are stored beside it) and it
     * behaves identically on every platform. Only if that cannot be created does
     * it fall back to the OS temp directory, and only when that one really is
     * writable — otherwise the null leaves PHP's behaviour untouched rather than
     * pointing it at another unusable path.
     */
    private function usableTempDir(): ?string
    {
        foreach ([storage_path('app/upload-tmp'), sys_get_temp_dir()] as $candidate) {
            if (! Str::of($candidate)->trim()) {
                continue;
            }

            if (! is_dir($candidate)) {
                @mkdir($candidate, 0775, true);
            }

            if (is_dir($candidate) && is_writable($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
