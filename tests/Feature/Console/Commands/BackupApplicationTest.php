<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->backups = storage_path('app/backups');
    $this->upload = storage_path('app/private/backup-test/evidence.pdf');
    File::ensureDirectoryExists(dirname($this->upload));
    File::put($this->upload, '%PDF-1.4 test');
    $this->before = File::glob($this->backups.'/car-backup-*.zip');
});

afterEach(function () {
    File::deleteDirectory(dirname($this->upload));

    foreach (array_diff(File::glob($this->backups.'/car-backup-*.zip'), $this->before) as $created) {
        File::delete($created);
    }
});

it('zips the uploaded files into storage/app/backups', function () {
    $this->artisan('app:backup')->assertSuccessful();

    $created = array_values(array_diff(File::glob($this->backups.'/car-backup-*.zip'), $this->before));
    $zip = new ZipArchive;
    $zip->open($created[0]);

    expect($created)->toHaveCount(1)
        ->and($zip->locateName('uploads/backup-test/evidence.pdf'))->not->toBeFalse();

    $zip->close();
});

it('removes backups older than the retention period', function () {
    File::ensureDirectoryExists($this->backups);
    $old = $this->backups.'/car-backup-20200101-010000.zip';
    File::put($old, 'old');
    touch($old, now()->subDays(20)->getTimestamp());

    $this->artisan('app:backup', ['--keep' => 14])->assertSuccessful();

    expect(File::exists($old))->toBeFalse();
});

it('runs every night', function () {
    $event = collect(app(Schedule::class)->events())->first(fn ($event) => str_contains($event->command, 'app:backup'));

    expect($event?->expression)->toBe('0 1 * * *');
});
