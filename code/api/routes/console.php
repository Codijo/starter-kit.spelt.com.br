<?php

use Illuminate\Support\Facades\Schedule;

// Cura drift de webhook perdido (o gate é local-first). Ver spec §8.3.
Schedule::command('spelt:reconcile')->hourly();
