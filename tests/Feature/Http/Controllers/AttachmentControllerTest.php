<?php

use App\Enums\Role;
use App\Models\Attachment;
use App\Models\Car;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->car = Car::factory()->create();
    $this->uploader = User::factory()->role(Role::Requestor)->create();
});

it('opens images, videos and PDFs in the browser', function () {
    $attachment = Attachment::store($this->car, UploadedFile::fake()->image('spoiled.jpg'), Attachment::PROBLEM_EVIDENCE, $this->uploader);

    $this->actingAs(User::factory()->role(Role::Monitor)->create())
        ->get(route('attachments.show', $attachment))
        ->assertOk()
        ->assertHeader('content-type', 'image/jpeg')
        ->assertHeader('content-disposition', 'inline; filename=spoiled.jpg');
});

it('downloads other files under their original name', function () {
    $attachment = Attachment::store($this->car, UploadedFile::fake()->create('dispatch.xlsx', 20), Attachment::PROBLEM_EVIDENCE, $this->uploader);

    $this->actingAs(User::factory()->role(Role::Monitor)->create())
        ->get(route('attachments.show', $attachment))
        ->assertOk()
        ->assertDownload('dispatch.xlsx');
});

it('sends guests to sign in', function () {
    $attachment = Attachment::store($this->car, UploadedFile::fake()->image('spoiled.jpg'), Attachment::PROBLEM_EVIDENCE, $this->uploader);

    $this->get(route('attachments.show', $attachment))->assertRedirect(route('login'));
});

it('returns 404 when the stored file is missing', function () {
    $attachment = Attachment::factory()->create(['attachable_id' => $this->car->id]);

    $this->actingAs(User::factory()->role(Role::Monitor)->create())
        ->get(route('attachments.show', $attachment))
        ->assertNotFound();
});

it('stores files outside the public folder', function () {
    $attachment = Attachment::store($this->car, UploadedFile::fake()->image('spoiled.jpg'), Attachment::PROBLEM_EVIDENCE, $this->uploader);

    expect($attachment->disk)->toBe('local')
        ->and($attachment->path)->toStartWith("cars/{$this->car->id}/")
        ->and($attachment->path)->not->toContain('spoiled');
});
