<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('monitor:actualizar-estados')->everyThirtySeconds(); //evalua estado de cada servidor segun su ultimo latido
