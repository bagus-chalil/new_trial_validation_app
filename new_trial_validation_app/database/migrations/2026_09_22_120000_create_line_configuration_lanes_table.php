<?php

use App\Models\MasterOption;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 3 of the RBAC/Team-master redesign (see memory
     * rbac_team_lane_redesign_2026_09_22 / the approved plan at
     * ~/.claude/plans/cheerful-twirling-lampson.md). Makes the Line
     * Configuration Report's two sign-off stages ("Approved (PIE)" /
     * "Checked (PROD)") admin-configurable instead of a hardcoded 'PROD'
     * string literal scattered across three files — an admin picks which
     * Team (from the same `master_options` type=reviewer_department rows
     * Phase 1 already made the Team master) is required for each stage, via
     * `required_team_id`. Exactly 2 fixed rows/stages, per the plan — only
     * the required team (and label) per stage is editable, not the number of
     * stages. `required_team_id=null` means "any active user", matching
     * today's actual Approved(PIE) behavior.
     *
     * Entirely new, Laravel-only table with no legacy equivalent — no
     * legacy-compatibility concern for this migration (see the plan's
     * "Legacy compatibility" section).
     */
    public function up(): void
    {
        if (! Schema::hasTable('line_configuration_lanes')) {
            Schema::create('line_configuration_lanes', function (Blueprint $table) {
                $table->increments('id');
                $table->string('stage_key', 20)->unique();
                $table->string('label', 100);
                $table->unsignedInteger('required_team_id')->nullable();
                $table->timestamps();
            });
        }

        // Seed exactly 2 rows reproducing today's actual behavior, so this
        // migration changes nothing observable on deploy, only makes it
        // editable afterward. Idempotent (firstOrCreate by stage_key).
        if (! DB::table('line_configuration_lanes')->where('stage_key', 'approved_pie')->exists()) {
            DB::table('line_configuration_lanes')->insert([
                'stage_key' => 'approved_pie',
                'label' => 'Approved (PIE)',
                'required_team_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if (! DB::table('line_configuration_lanes')->where('stage_key', 'checked_prod')->exists()) {
            $prodTeamId = MasterOption::query()
                ->where('type', 'reviewer_department')
                ->whereRaw('UPPER(TRIM(name)) = ?', [User::normalizeDepartment('PROD')])
                ->value('id');

            DB::table('line_configuration_lanes')->insert([
                'stage_key' => 'checked_prod',
                'label' => 'Checked (PROD)',
                'required_team_id' => $prodTeamId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('line_configuration_lanes');
    }
};
