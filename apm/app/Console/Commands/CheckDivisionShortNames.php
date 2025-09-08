<?php

namespace App\Console\Commands;

use App\Models\Division;
use Illuminate\Console\Command;

class CheckDivisionShortNames extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'check:division-short-names';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check which divisions have short names and which need them';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking Division Short Names');
        $this->newLine();

        $divisions = Division::all();
        
        if ($divisions->isEmpty()) {
            $this->warn('No divisions found in the database.');
            return 0;
        }

        $withShortNames = $divisions->filter(function ($division) {
            return !empty($division->division_short_name);
        });

        $withoutShortNames = $divisions->filter(function ($division) {
            return empty($division->division_short_name);
        });

        $this->info("Total Divisions: {$divisions->count()}");
        $this->info("With Short Names: {$withShortNames->count()}");
        $this->info("Without Short Names: {$withoutShortNames->count()}");
        $this->newLine();

        if ($withShortNames->isNotEmpty()) {
            $this->info('Divisions WITH short names:');
            $this->table(
                ['ID', 'Division Name', 'Short Name'],
                $withShortNames->map(function ($division) {
                    return [
                        $division->id,
                        $division->division_name,
                        $division->division_short_name
                    ];
                })->toArray()
            );
            $this->newLine();
        }

        if ($withoutShortNames->isNotEmpty()) {
            $this->warn('Divisions WITHOUT short names:');
            $this->table(
                ['ID', 'Division Name', 'Short Name'],
                $withoutShortNames->map(function ($division) {
                    return [
                        $division->id,
                        $division->division_name,
                        $division->division_short_name ?: 'NULL'
                    ];
                })->toArray()
            );
            $this->newLine();
            
            $this->warn('These divisions need short names for document numbering to work properly.');
            $this->info('You can generate short names using: php artisan settings:force_generate_short_names');
        }

        if ($withoutShortNames->isEmpty()) {
            $this->info('✅ All divisions have short names! Document numbering will work properly.');
        }

        return 0;
    }
}