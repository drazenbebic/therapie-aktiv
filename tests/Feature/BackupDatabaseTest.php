<?php

namespace Tests\Feature;

use App\Models\Patient;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\File;
use PDO;
use Tests\TestCase;

/**
 * Uses DatabaseMigrations instead of RefreshDatabase, because SQLite can't VACUUM inside a transaction.
 */
class BackupDatabaseTest extends TestCase
{
    use DatabaseMigrations;

    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = sys_get_temp_dir().'/therapie-aktiv-backup-test-'.uniqid();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->directory);

        parent::tearDown();
    }

    public function test_it_writes_a_consistent_copy_of_the_database(): void
    {
        Patient::factory()->count(3)->create();

        $this->artisan('app:backup-database', ['--dir' => $this->directory])->assertSuccessful();

        $files = File::files($this->directory);
        $this->assertCount(1, $files);
        $this->assertMatchesRegularExpression('/^therapie-aktiv-\d{4}-\d{2}-\d{2}_\d{6}\.sqlite$/', $files[0]->getFilename());

        $copy = new PDO('sqlite:'.$files[0]->getPathname());
        $this->assertSame(3, (int) $copy->query('select count(*) from patients')->fetchColumn());
    }

    public function test_it_keeps_only_the_newest_backups(): void
    {
        File::ensureDirectoryExists($this->directory);

        foreach (['2026-01-01_100000', '2026-02-01_100000', '2026-03-01_100000'] as $stamp) {
            File::put("{$this->directory}/therapie-aktiv-{$stamp}.sqlite", 'old');
        }

        File::put("{$this->directory}/unrelated.txt", 'keep me');

        $this->artisan('app:backup-database', ['--dir' => $this->directory, '--keep' => 2])->assertSuccessful();

        $names = collect(File::files($this->directory))->map->getFilename()->sort()->values()->all();

        $this->assertCount(3, $names);
        $this->assertContains('unrelated.txt', $names);
        $this->assertContains('therapie-aktiv-2026-03-01_100000.sqlite', $names);
        $this->assertNotContains('therapie-aktiv-2026-01-01_100000.sqlite', $names);
        $this->assertNotContains('therapie-aktiv-2026-02-01_100000.sqlite', $names);
    }
}
