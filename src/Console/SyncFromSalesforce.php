<?php

namespace Lester\EloquentSalesForce\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\InputOption;

class SyncFromSalesforce extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'db:sync';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Trigger a sync of all connected local and SF objects.';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        foreach($this->getModels() as $class) {
            $collection = $class::whereNotNull((new $class)->getSalesforceIdField())->get();
            foreach ($collection as $model) {
                $model->syncWithSalesforce();
            }
        }
    }

    private function getModels()
    {
        return config('eloquent_sf.syncTwoWayModels', []);
    }

}
