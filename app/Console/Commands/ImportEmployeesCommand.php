<?php

namespace App\Console\Commands;

use App\Models\EmploymentPeriodRecord;
use App\Services\Csv\CsvReader;
use App\Services\DateFormatDetector;
use App\Services\Employees\EmploymentPeriodReader;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\LazyCollection;

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

        $this->components->task('Clearing previously imported data', function () {
            EmploymentPeriodRecord::truncate();
        });

        $path = $disk->path($chosen);
        $reader = new EmploymentPeriodReader(new CsvReader(), new DateFormatDetector());
        $imported = 0;

        $this->components->task("Importing {$chosen}", function () use ($reader, $path, &$imported) {
            LazyCollection::make(fn () => $reader->read($path))
                ->chunk(500)
                ->each(function ($chunk) use (&$imported) {
                    DB::table('employment_periods')->insert(
                        $chunk->map(fn ($period) => [
                            'emp_id' => $period->empId,
                            'project_id' => $period->projectId,
                            'date_from' => $period->dateFrom->format('Y-m-d'),
                            'date_to' => $period->dateTo->format('Y-m-d'),
                        ])->all()
                    );

                    $imported += $chunk->count();
                });
        });

        $this->components->info("Imported {$imported} employment period rows from {$chosen}.");

        return self::SUCCESS;
    }
}
