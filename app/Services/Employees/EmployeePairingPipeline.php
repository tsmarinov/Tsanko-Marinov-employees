<?php

namespace App\Services\Employees;

use App\Services\Csv\CsvReader;
use App\Services\DateFormatDetector;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;

class EmployeePairingPipeline
{
    /**
     * Run the full pipeline for a CSV file: clear previous data, stream and
     * import the file, consolidate overlapping periods, then find the
     * winning pair. Shared by the CLI import command and the web upload
     * endpoint so both stay in sync.
     *
     * @return array{imported: int, merged: int, winner: array{empA: int, empB: int, totalDays: int, breakdown: array<int, array{projectId: int, days: int}>}|null}
     */
    public function run(string $csvPath): array
    {
        DB::table('employment_periods')->delete();

        $reader = new EmploymentPeriodReader(new CsvReader(), new DateFormatDetector());
        $imported = 0;

        LazyCollection::make(fn () => $reader->read($csvPath))
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

        $merged = (new EmploymentPeriodConsolidator())->consolidate();
        $winner = (new EmployeePairFinder())->findWinningPair();

        return [
            'imported' => $imported,
            'merged' => $merged,
            'winner' => $winner,
        ];
    }
}
