<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    public function download(Request $request, Attachment $attachment): StreamedResponse
    {
        Gate::authorize('download', $attachment);

        if (! Storage::disk('local')->exists($attachment->file_path)) {
            abort(404, 'The requested attachment could not be found.');
        }

        return Storage::disk('local')->response(
            $attachment->file_path,
            $attachment->file_name,
            ['Content-Type' => $attachment->mime_type ?: 'application/octet-stream']
        );
    }
}

