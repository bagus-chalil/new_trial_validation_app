<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use InvalidArgumentException;

/**
 * Shared upload checks of the Projects module (project files and improvement
 * files): script-like names rejected anywhere in the file name, extension
 * whitelist, size cap, and detected MIME must match the extension. Throws
 * InvalidArgumentException with a translated message when refused.
 */
final class ProjectUploadGuard
{
    /**
     * @return array{extension: string, mime: string}
     */
    public static function check(UploadedFile $upload): array
    {
        $originalName = $upload->getClientOriginalName();
        $extension = strtolower($upload->getClientOriginalExtension());

        if (preg_match(ProjectOptions::DANGEROUS_NAME_PATTERN, $originalName) === 1) {
            throw new InvalidArgumentException(__('projects.upload.dangerous', ['name' => $originalName]));
        }

        if ($extension === '' || ! array_key_exists($extension, ProjectOptions::UPLOAD_MIMES)) {
            throw new InvalidArgumentException(__('projects.upload.type_not_allowed', ['name' => $originalName]));
        }

        if (! $upload->isValid() || $upload->getSize() > ProjectOptions::MAX_UPLOAD_BYTES) {
            throw new InvalidArgumentException(__('projects.upload.too_large', ['name' => $originalName]));
        }

        $detected = strtolower((string) ($upload->getMimeType() ?: 'application/octet-stream'));

        if (! in_array($detected, ProjectOptions::UPLOAD_MIMES[$extension], true)) {
            throw new InvalidArgumentException(__('projects.upload.mime_mismatch', ['name' => $originalName, 'mime' => $detected, 'ext' => $extension]));
        }

        return ['extension' => $extension, 'mime' => $detected];
    }
}
