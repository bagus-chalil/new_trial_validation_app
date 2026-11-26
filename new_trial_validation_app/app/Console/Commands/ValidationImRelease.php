<?php

namespace App\Console\Commands;

use App\Actions\ImMaster\ImRelease;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class ValidationImRelease extends Command
{
    protected $signature = 'validation:im-release';

    protected $description = 'Make approved (Scheduled) IM revisions Effective once their release date has come';

    public function handle(ImRelease $release): int
    {
        if (! Schema::hasTable('vd_im_revisions')) {
            $this->warn('vd_im_revisions does not exist yet; nothing to do.');

            return self::SUCCESS;
        }

        $this->info('Released '.$release->releaseDue().' IM revision(s).');

        return self::SUCCESS;
    }
}
