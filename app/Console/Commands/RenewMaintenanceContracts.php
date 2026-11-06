<?php

namespace App\Console\Commands;

use App\Models\MaintenanceContract;
use Illuminate\Console\Command;

class RenewMaintenanceContracts extends Command
{
    protected $signature = 'contracts:auto-renew';

    protected $description = 'Roll expired auto-renew maintenance contracts into their next term';

    public function handle(): int
    {
        $renewed = MaintenanceContract::renewExpired();

        foreach ($renewed as $c) {
            $this->line("{$c->po_no} / {$c->serial_no}: {$c->start_date->format('Y-m-d')} to {$c->end_date->format('Y-m-d')}");
        }

        $this->info(count($renewed) . ' contract(s) renewed.');

        return self::SUCCESS;
    }
}
