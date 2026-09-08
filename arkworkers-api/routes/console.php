<?php

use App\Models\Asset;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Permanently removes decommissioned assets (and, via cascade, their
// routine/task/proof history) 30 days after decommissioning, per the
// grace-period policy in Asset::prunable().
Schedule::command('model:prune', ['--model' => Asset::class])->daily();
