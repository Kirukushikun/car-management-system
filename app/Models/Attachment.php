<?php

namespace App\Models;

use Database\Factories\AttachmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * A file on the private disk, attached to a CAR (or later to a round or corrective action).
 * Never linked publicly — downloads go through AttachmentController, which checks the CAR.
 */
#[Fillable(['collection', 'disk', 'path', 'original_name', 'mime_type', 'size', 'uploaded_by'])]
class Attachment extends Model
{
    /** @use HasFactory<AttachmentFactory> */
    use HasFactory;

    /**
     * Phase I evidence attached when the CAR is filed.
     */
    public const PROBLEM_EVIDENCE = 'problem_evidence';

    /**
     * Step 8 root-cause findings attached to a response (the requirement allows text or a file).
     */
    public const ROOT_CAUSE = 'root_cause';

    /**
     * Step 9 corrective-action documents attached to a response.
     */
    public const CORRECTIVE_ACTION = 'corrective_action';

    /**
     * File types accepted as CAR evidence: photos, phone videos, screenshots, PDFs, Office files.
     *
     * @var list<string>
     */
    public const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'heic', 'mp4', 'mov', 'pdf', 'doc', 'docx', 'xls', 'xlsx'];

    public const MAX_KILOBYTES = 51200;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }

    /**
     * Store an uploaded file on the private disk and attach it to the owner.
     */
    public static function store(Model $owner, UploadedFile $file, string $collection, User $uploader): self
    {
        $directory = Str::of($owner->getMorphClass())->classBasename()->snake()->plural().'/'.$owner->getKey();
        $path = $file->storeAs($directory, Str::uuid().'.'.strtolower($file->getClientOriginalExtension()), 'local');

        return $owner->attachments()->create([
            'collection' => $collection,
            'disk' => 'local',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
            'size' => $file->getSize(),
            'uploaded_by' => $uploader->id,
        ]);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * The CAR this file belongs to, whatever it is attached to directly.
     */
    public function car(): ?Car
    {
        $owner = $this->attachable;

        return $owner instanceof Car ? $owner : $owner?->car;
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    public function isVideo(): bool
    {
        return str_starts_with($this->mime_type, 'video/');
    }

    /**
     * Images, videos and PDFs open in the browser; everything else downloads.
     */
    public function opensInline(): bool
    {
        return $this->isImage() || $this->isVideo() || $this->mime_type === 'application/pdf';
    }

    public function humanSize(): string
    {
        return $this->size >= 1048576
            ? number_format($this->size / 1048576, 1).' MB'
            : max(1, (int) round($this->size / 1024)).' KB';
    }
}
