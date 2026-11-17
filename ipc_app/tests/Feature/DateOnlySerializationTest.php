<?php

namespace Tests\Feature;

use App\Models\IpcBatch;
use Tests\TestCase;

class DateOnlySerializationTest extends TestCase
{
    /** Regression: under a non-UTC APP_TIMEZONE the ISO form of a date-only value shifted back one day. */
    public function test_date_only_attributes_serialize_without_timezone_shift(): void
    {
        $original = config('app.timezone');
        config(['app.timezone' => 'Asia/Jakarta']);
        date_default_timezone_set('Asia/Jakarta');

        try {
            $batch = new IpcBatch;
            $batch->setRawAttributes(['mixing_date' => '2026-10-23', 'exp_date' => '2029-10-23']);

            $array = $batch->toArray();
            $this->assertSame('2026-10-23', $array['mixing_date']);
            $this->assertSame('2029-10-23', $array['exp_date']);
        } finally {
            config(['app.timezone' => $original]);
            date_default_timezone_set($original);
        }
    }
}
