<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Evidence file of a Mixing record (D8: batch record revision proof is
 * uploaded here, category `Revisi Batch Record`). Stored on the private
 * `local` disk under mixing/{id}/{file_name}, served through a policy-gated route.
 *
 * @property int $id
 * @property int $mixing_id
 * @property string $category
 * @property string $original_name
 * @property string $file_name
 * @property string $mime
 * @property int $size_bytes
 * @property string|null $caption
 * @property string $uploaded_by
 */
class MixingFile extends Model
{
    public const CATEGORIES = ['Revisi Batch Record', 'Foto Proses', 'Hasil Uji', 'Lainnya'];

    protected $table = 'vd_mixing_files';

    protected $guarded = [];
}
