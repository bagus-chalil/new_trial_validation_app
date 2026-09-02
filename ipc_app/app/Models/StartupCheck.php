<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StartupCheck extends Model
{
    public const STATUS_AVAILABLE = 'Available';

    public const STATUS_NOT_AVAILABLE = 'Not Available';

    public const STATUS_CONFORM = 'Conform';

    public const STATUS_NOT_CONFORM = 'Not Conform';

    protected $fillable = [
        'ipc_batch_id',
        'user_id',
        'product_standard_status',
        'sample_challenge_test_status',
        'wi_im_match_status',
        'pm_bom_match_status',
        'bulk_status_status',
        'machine_vision_status',
        'machine_weigher_status',
        'machine_roller_status',
        'machine_load_cell_status',
        'machine_balance_status',
        'validation_report_status',
        'identity_line_board_status',
        'filling_range_min',
        'filling_range_max',
        'density',
        'heating',
        'line_leader_name',
        'operator_name',
        'im_number',
        'color',
        'coding',
        'temperature_setting',
        'remarks',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'filling_range_min' => 'decimal:2',
            'filling_range_max' => 'decimal:2',
            'density' => 'decimal:4',
            'completed_at' => 'datetime',
        ];
    }

    public function batch()
    {
        return $this->belongsTo(IpcBatch::class, 'ipc_batch_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function bottleWeights()
    {
        return $this->hasMany(StartupBottleWeight::class);
    }
}
