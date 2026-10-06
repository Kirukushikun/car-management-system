<?php

namespace App\Http\Controllers;

use App\Models\AccessLog;
use App\Models\Attachment;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    /**
     * Serve a CAR attachment to anyone allowed to view its CAR. Images, videos and PDFs open
     * in the browser; other files download under their original name.
     */
    public function __invoke(Attachment $attachment): StreamedResponse
    {
        $car = $attachment->car();

        abort_if($car === null, 404);
        Gate::authorize('view', $car);

        $disk = Storage::disk($attachment->disk);
        abort_unless($disk->exists($attachment->path), 404);

        AccessLog::recordAccess('download', "{$car->reference} · {$attachment->original_name}");

        $headers = ['Content-Type' => $attachment->mime_type];

        return $attachment->opensInline()
            ? $disk->response($attachment->path, $attachment->original_name, $headers)
            : $disk->download($attachment->path, $attachment->original_name, $headers);
    }
}
