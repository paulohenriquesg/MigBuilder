<?php

namespace MigBuilder\Console;

use Illuminate\Console\Command;
use MigBuilder\Builder;

class RunCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migbuilder:build {connection : The name of the database connection where tables are stored}, {--overwrite}, {--timestamps}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Builds Model, Factory, Seeder & Migration for the specified MySQL table';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $b = new Builder($this, $this->argument('connection'));
        $this->line("Migbuilder starting...");
        $b->buildDatabase($this->option('timestamps'), $this->option('overwrite'));
        $this->info("Migbuilder finished...");

        return true;
    }

}
