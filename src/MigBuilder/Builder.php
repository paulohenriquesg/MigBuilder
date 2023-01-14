<?php

namespace MigBuilder;

use Illuminate\Console\Command;
use function Termwind\terminal;

class Builder
{
    private ?Explorer $explorer;
    private Command $command;

    public function __construct(Command $command, ?string $connection = null)
    {
        $this->explorer = new Explorer($connection);
        $this->command = $command;
    }

    public function buildDatabase(bool $timestamps = true, bool $overwrite = false): void
    {
        $tables = $this->explorer->listSortedTables();
        $index = 0;
        foreach ($tables as $table) {
            $dots = max(terminal()->width() - mb_strlen($table) - 8, 0);
            $this->command->line($table . ' ' . str_repeat('<fg=gray>.</>', $dots) . ' <fg=green;options=bold>DONE</>');

            $index++;
            $this->buildAll($table, $index, $timestamps, $overwrite);
        }
    }

    private function buildAll(string $table, int $index, bool $timestamps = true, bool $overwrite = false): void
    {
        $modelFile = app_path() . '/Models/' . $this->modelFileName($table);
        $factoryFile = database_path() . '/factories/' . $this->factoryFileName($table);
        $seederFile = database_path() . '/seeders/' . $this->seederFileName($table);
        $migrationFileName = $this->getExistingMigrationFileName($table);
        $migrationFile = database_path() . '/migrations/' . $migrationFileName;
        if ($overwrite === false && (
                file_exists($modelFile) ||
                file_exists($factoryFile) ||
                file_exists($seederFile) ||
                ($migrationFileName !== false && file_exists($migrationFile))
            )) {
            die("One of the files to be generated exists and overwrite option was not specified.");
        }

        @unlink($modelFile);
        @unlink($factoryFile);
        @unlink($seederFile);
        @unlink($migrationFile);

        $this->buildModel($table, $timestamps);
        $this->buildFactory($table);
        $this->buildSeeder($table);
        $this->buildMigration($table, $index, $timestamps);
    }

    private function buildModel(string $table, bool $timestamps): void
    {
        $columns = $this->explorer->listColumns($table);
        $constraints = $this->explorer->listConstraints($table);
        $children = [];
        $tables = $this->explorer->listTables();
        foreach ($tables as $t) {
            if (isset($t['dependencies'][$table])) {
                $children[] = $t['name'];
            }
        }
        $code = Renderer::model($table, $columns, $constraints, $children, $timestamps);
        file_put_contents(app_path() . '/Models/' . $this->modelFileName($table), $code);
    }

    private function buildFactory(string $table): void
    {
        $columns = $this->explorer->listColumns($table);
        $constraints = $this->explorer->listConstraints($table);
        $code = Renderer::factory($table, $columns, $constraints);
        file_put_contents(database_path() . '/factories/' . $this->factoryFileName($table), $code);
    }

    private function buildSeeder(string $table): void
    {
        $columns = $this->explorer->listColumns($table);
        $code = Renderer::seeder($table, $columns);
        file_put_contents(database_path() . '/seeders/' . $this->seederFileName($table), $code);
    }

    private function buildMigration(string $table, int $index, bool $timestamps = true): void
    {
        $columns = $this->explorer->listColumns($table);
        $constraints = $this->explorer->listConstraints($table);
        $code = Renderer::migration($table, $columns, $constraints, $timestamps);
        file_put_contents(database_path() . '/migrations/' . $this->migrationFileName($table, $index), $code);
    }

    private function modelFileName(string $table): string
    {
        return Util::firstUpper($table) . ".php";
    }

    private function factoryFileName(string $table): string
    {
        return Util::firstUpper($table) . "Factory.php";
    }

    private function seederFileName(string $table): string
    {
        return Util::firstUpper($table) . "Seeder.php";
    }

    private function migrationFileName(string $table, ?int $index = null): string
    {
        if ($index === null) {
            $idx = date("His");
        } else {
            $idx = substr("000000" . $index, -6);
        }
        $date = date("Y_m_d_");
        return $date . $idx . "_create_" . strtolower($table) . "_table.php";
    }

    private function getExistingMigrationFileName(string $table)
    {
        $pattern = "_create_" . strtolower($table) . "_table.php";
        $filename = false;
        $hd = dir(database_path() . '/migrations/');
        while (false !== ($entry = $hd->read())) {
            if (substr($entry, -strlen($pattern)) == $pattern) {
                $filename = $entry;
                break;
            }
        }
        return $filename;
    }
}
