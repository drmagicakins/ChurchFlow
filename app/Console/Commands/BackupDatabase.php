<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

/**
 * §49-50/§10 production requirements: deliberately minimal — a single
 * mysqldump piped to the 'private' disk, timestamped, with no retention
 * policy, encryption, or off-site replication built in. This is NOT what
 * should run in production; use a real backup solution there
 * (spatie/laravel-backup with an S3/off-site destination, or your
 * infrastructure provider's managed database backups — most managed MySQL
 * offerings already do point-in-time recovery better than this script
 * ever could). This command exists so `php artisan backup:database` is
 * never literally undefined, and as the obvious place to wire a real
 * solution in later without inventing a new command name.
 */
class BackupDatabase extends Command
{
    protected $signature = 'backup:database';

    protected $description = 'Dumps the database to the private disk. See this command\'s own docblock before relying on it in production.';

    public function handle(): int
    {
        $connection = config('database.default');
        $config = config("database.connections.{$connection}");

        if ($config['driver'] !== 'mysql') {
            $this->error('This minimal backup command only supports mysqldump. Use a proper backup solution for other drivers.');

            return self::FAILURE;
        }

        $filename = 'backups/'.now()->format('Y-m-d_His').'.sql';

        $result = Process::run([
            'mysqldump',
            '-h', $config['host'],
            '-P', (string) $config['port'],
            '-u', $config['username'],
            '--password='.$config['password'],
            $config['database'],
        ])->output();

        if (empty($result)) {
            $this->error('mysqldump produced no output — check credentials and that mysqldump is installed.');

            return self::FAILURE;
        }

        Storage::disk('private')->put($filename, $result);
        $this->info("Backup written to the private disk at: {$filename}");

        return self::SUCCESS;
    }
}
