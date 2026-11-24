<?php

namespace App\Support;

/**
 * Stored vocabularies of the Projects module. These values are shared with
 * the legacy Project Tracking data (and imported rows), so they stay raw in
 * the database and are only translated for display on the frontend.
 */
final class ProjectOptions
{
    public const PROJECT_STATUSES = ['Not Started', 'On Progress', 'On Hold', 'Delayed', 'Completed'];

    public const PRIORITIES = ['Low', 'Medium', 'High', 'Critical'];

    public const STAGE_STATUSES = ['Not Started', 'On Progress', 'On Hold', 'Delayed', 'Completed'];

    public const STAGE_TYPES = ['General', 'Validation'];

    public const MILESTONE_STATUSES = ['Pending', 'Done'];

    public const QUOTATION_STATUSES = ['Review', 'Diterima', 'Terpilih', 'Ditolak'];

    public const FILE_CONTEXTS = ['project', 'stage', 'quotation'];

    public const MAX_UPLOAD_BYTES = 100 * 1024 * 1024;

    /**
     * Allowed upload extensions => acceptable detected MIME types. Ported
     * from the legacy app's ALLOWED_UPLOAD_MIMES whitelist.
     *
     * @var array<string, list<string>>
     */
    public const UPLOAD_MIMES = [
        'png' => ['image/png'],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'gif' => ['image/gif'],
        'webp' => ['image/webp'],
        'pdf' => ['application/pdf'],
        'mp4' => ['video/mp4'],
        'webm' => ['video/webm'],
        'mov' => ['video/quicktime', 'video/mp4'],
        'm4v' => ['video/x-m4v', 'video/mp4'],
        'txt' => ['text/plain'],
        'csv' => ['text/plain', 'text/csv', 'application/csv', 'application/vnd.ms-excel'],
        'doc' => ['application/msword', 'application/vnd.ms-office', 'application/octet-stream'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream'],
        'xls' => ['application/vnd.ms-excel', 'application/vnd.ms-office', 'application/octet-stream'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/octet-stream'],
        'ppt' => ['application/vnd.ms-powerpoint', 'application/vnd.ms-office', 'application/octet-stream'],
        'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip', 'application/octet-stream'],
    ];

    /** Extensions that may be previewed inline; everything else downloads. */
    public const INLINE_TYPES = [
        'pdf' => 'application/pdf',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'mp4' => 'video/mp4',
        'webm' => 'video/webm',
        'mov' => 'video/quicktime',
        'm4v' => 'video/x-m4v',
        'txt' => 'text/plain; charset=utf-8',
    ];

    /** Script-like names rejected anywhere in the file name (double extension tricks). */
    public const DANGEROUS_NAME_PATTERN = '/\.(php\d*|phtml|phar|cgi|pl|py|sh|bat|cmd|exe|dll|jsp|asp|aspx|htaccess|htpasswd|html?|svg|js)(\.|$)/i';
}
