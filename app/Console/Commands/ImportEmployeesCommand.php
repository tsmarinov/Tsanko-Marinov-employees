<?php

namespace App\Console\Commands;

use App\Services\Employees\EmployeePairingPipeline;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

use function Laravel\Prompts\select;

class ImportEmployeesCommand extends Command
{
    protected $signature = 'employees:import';

    protected $description = 'Pick a CSV file from storage/app/csv-input and import it, replacing any previously imported data';

    public function handle(): int
    {
        $disk = Storage::disk('csv_input');

        $files = collect($disk->files())
            ->filter(fn (string $file) => strtolower(pathinfo($file, PATHINFO_EXTENSION)) === 'csv')
            ->values();

        if ($files->isEmpty()) {
            $this->components->error(
                "No CSV files found in {$disk->path('')}. Place a file there and try again."
            );

            return self::FAILURE;
        }

        $chosen = select(
            label: 'Which file do you want to import?',
            options: $files->all(),
        );

        $path = $disk->path($chosen);
        $result = null;

        $this->components->task("Processing {$chosen}", function () use ($path, &$result) {
            $result = (new EmployeePairingPipeline())->run($path);
        });

        $this->components->info(
            "Imported {$result['imported']} rows, consolidated into {$result['merged']} non-overlapping employee/project periods."
        );

        $winner = $result['winner'];

        if ($winner === null) {
            $this->components->warn('No two employees ever worked together on a shared project.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->line("<fg=green;options=bold>{$winner['empA']}, {$winner['empB']}, {$winner['totalDays']}</>");
        $this->newLine();

        $this->table(
            ['Employee ID #1', 'Employee ID #2', 'Project ID', 'Days worked'],
            collect($winner['breakdown'])->map(fn (array $row) => [
                $winner['empA'], $winner['empB'], $row['projectId'], $row['days'],
            ])->all()
        );

        return self::SUCCESS;
    }
}
