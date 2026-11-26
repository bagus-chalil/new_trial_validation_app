<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Drawing / photo / source document attached to an IM revision. Stored on the
 * private `local` disk under im/{revision_id}/{file_name}.
 *
 * @property int $id
 * @property int $revision_id
 * @property string $category
 * @property string $caption
 * @property string $original_name
 * @property string $file_name
 * @property string $mime
 * @property int $size
 */
class ImFile extends Model
{
    public const CATEGORIES = ['appearance_filling', 'primary_packaging', 'secondary_packaging', 'tertiary_packaging', 'source_document'];

    protected $table = 'vd_im_files';

    protected $guarded = [];
}
