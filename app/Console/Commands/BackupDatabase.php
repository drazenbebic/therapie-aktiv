<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use SplFileInfo;

/**
 * Writes a consistent copy of the SQLite database (safe while the app is running) and prunes old copies.
 */
#[Signature('app:backup-database {--dir= : Target directory (default: DB_BACKUP_PATH)} {--keep=30 : Number of backups to keep}')]
#[Description('Write a consistent copy of the SQLite database and delete old copies')]
class BackupDatabase extends Command
{
    private const string PREFIX = 'therapie-aktiv-';

    public function handle(): int
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            $this->error('Only SQLite databases can be backed up with this command.');

            return self::FAILURE;
        }

        $directory = $this->option('dir') ?: config('database.backup_path');
        $keep = max(1, (int) $this->option('keep'));

        File::ensureDirectoryExists($directory);

        $target = $directory.'/'.self::PREFIX.now()->format('Y-m-d_His').'.sqlite';

        DB::statement('VACUUM INTO ?', [$target]);

        $this->info("Backup written to {$target}");

        $this->pruneOldBackups($directory, $keep);

        return self::SUCCESS;
    }

    private function pruneOldBackups(string $directory, int $keep): void
    {
        collect(File::files($directory))
            ->filter(fn (SplFileInfo $file): bool => str_starts_with($file->getFilename(), self::PREFIX) && $file->getExtension() === 'sqlite')
            ->sortByDesc(fn (SplFileInfo $file): string => $file->getFilename())
            ->slice($keep)
            ->each(function (SplFileInfo $file): void {
                File::delete($file->getPathname());
                $this->line("Deleted old backup {$file->getFilename()}");
            });
    }
}
