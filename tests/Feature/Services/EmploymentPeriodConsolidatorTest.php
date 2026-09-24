<?php

namespace Tests\Feature\Services;

use App\Services\Employees\EmploymentPeriodConsolidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EmploymentPeriodConsolidatorTest extends TestCase
{
    use RefreshDatabase;

    private function seedRaw(array $rows): void
    {
        foreach ($rows as $row) {
            DB::table('employment_periods')->insert($row);
        }
    }

    private function merged(): array
    {
        return DB::table('merged_employment_periods')
            ->orderBy('emp_id')->orderBy('project_id')->orderBy('date_from')
            ->get(['emp_id', 'project_id', 'date_from', 'date_to'])
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    public function test_merges_overlapping_periods(): void
    {
        $this->seedRaw([
            ['emp_id' => 1, 'project_id' => 10, 'date_from' => '2020-01-01', 'date_to' => '2020-01-10'],
            ['emp_id' => 1, 'project_id' => 10, 'date_from' => '2020-01-05', 'date_to' => '2020-01-15'],
        ]);

        $count = (new EmploymentPeriodConsolidator())->consolidate();

        $this->assertSame(1, $count);
        $this->assertSame([
            ['emp_id' => 1, 'project_id' => 10, 'date_from' => '2020-01-01', 'date_to' => '2020-01-15'],
        ], $this->merged());
    }

    public function test_merges_touching_periods(): void
    {
        $this->seedRaw([
            ['emp_id' => 1, 'project_id' => 10, 'date_from' => '2020-01-01', 'date_to' => '2020-01-10'],
            ['emp_id' => 1, 'project_id' => 10, 'date_from' => '2020-01-10', 'date_to' => '2020-01-20'],
        ]);

        (new EmploymentPeriodConsolidator())->consolidate();

        $this->assertSame([
            ['emp_id' => 1, 'project_id' => 10, 'date_from' => '2020-01-01', 'date_to' => '2020-01-20'],
        ], $this->merged());
    }

    public function test_keeps_a_real_gap_as_two_separate_periods(): void
    {
        $this->seedRaw([
            ['emp_id' => 1, 'project_id' => 10, 'date_from' => '2020-01-01', 'date_to' => '2020-01-10'],
            ['emp_id' => 1, 'project_id' => 10, 'date_from' => '2020-01-12', 'date_to' => '2020-01-20'],
        ]);

        $count = (new EmploymentPeriodConsolidator())->consolidate();

        $this->assertSame(2, $count);
        $this->assertSame([
            ['emp_id' => 1, 'project_id' => 10, 'date_from' => '2020-01-01', 'date_to' => '2020-01-10'],
            ['emp_id' => 1, 'project_id' => 10, 'date_from' => '2020-01-12', 'date_to' => '2020-01-20'],
        ], $this->merged());
    }

    public function test_bridges_three_periods_via_the_middle_one(): void
    {
        $this->seedRaw([
            ['emp_id' => 1, 'project_id' => 10, 'date_from' => '2020-01-01', 'date_to' => '2020-01-05'],
            ['emp_id' => 1, 'project_id' => 10, 'date_from' => '2020-01-10', 'date_to' => '2020-01-15'],
            ['emp_id' => 1, 'project_id' => 10, 'date_from' => '2020-01-04', 'date_to' => '2020-01-11'],
        ]);

        (new EmploymentPeriodConsolidator())->consolidate();

        $this->assertSame([
            ['emp_id' => 1, 'project_id' => 10, 'date_from' => '2020-01-01', 'date_to' => '2020-01-15'],
        ], $this->merged());
    }

    public function test_merges_a_nested_interval_correctly(): void
    {
        // [1-20] fully contains [5-8], and [10-12] sits between them; the
        // running-MAX fix ensures this collapses to one period instead of
        // being wrongly split by the shorter row in the middle.
        $this->seedRaw([
            ['emp_id' => 1, 'project_id' => 10, 'date_from' => '2020-01-01', 'date_to' => '2020-01-20'],
            ['emp_id' => 1, 'project_id' => 10, 'date_from' => '2020-01-05', 'date_to' => '2020-01-08'],
            ['emp_id' => 1, 'project_id' => 10, 'date_from' => '2020-01-10', 'date_to' => '2020-01-12'],
        ]);

        $count = (new EmploymentPeriodConsolidator())->consolidate();

        $this->assertSame(1, $count);
        $this->assertSame([
            ['emp_id' => 1, 'project_id' => 10, 'date_from' => '2020-01-01', 'date_to' => '2020-01-20'],
        ], $this->merged());
    }

    public function test_collapses_exact_duplicate_rows(): void
    {
        $this->seedRaw([
            ['emp_id' => 1, 'project_id' => 10, 'date_from' => '2020-01-01', 'date_to' => '2020-01-10'],
            ['emp_id' => 1, 'project_id' => 10, 'date_from' => '2020-01-01', 'date_to' => '2020-01-10'],
        ]);

        $count = (new EmploymentPeriodConsolidator())->consolidate();

        $this->assertSame(1, $count);
        $this->assertSame([
            ['emp_id' => 1, 'project_id' => 10, 'date_from' => '2020-01-01', 'date_to' => '2020-01-10'],
        ], $this->merged());
    }

    public function test_ties_on_identical_date_from_still_merge_correctly(): void
    {
        $this->seedRaw([
            ['emp_id' => 1, 'project_id' => 10, 'date_from' => '2020-01-10', 'date_to' => '2020-01-11'],
            ['emp_id' => 1, 'project_id' => 10, 'date_from' => '2020-01-10', 'date_to' => '2020-01-30'],
        ]);

        (new EmploymentPeriodConsolidator())->consolidate();

        $this->assertSame([
            ['emp_id' => 1, 'project_id' => 10, 'date_from' => '2020-01-10', 'date_to' => '2020-01-30'],
        ], $this->merged());
    }

    public function test_does_not_merge_different_projects_for_the_same_employee(): void
    {
        $this->seedRaw([
            ['emp_id' => 1, 'project_id' => 10, 'date_from' => '2020-01-01', 'date_to' => '2020-01-10'],
            ['emp_id' => 1, 'project_id' => 20, 'date_from' => '2020-01-01', 'date_to' => '2020-01-10'],
        ]);

        $count = (new EmploymentPeriodConsolidator())->consolidate();

        $this->assertSame(2, $count);
    }

    public function test_does_not_merge_different_employees_on_the_same_project(): void
    {
        $this->seedRaw([
            ['emp_id' => 1, 'project_id' => 10, 'date_from' => '2020-01-01', 'date_to' => '2020-01-10'],
            ['emp_id' => 2, 'project_id' => 10, 'date_from' => '2020-01-01', 'date_to' => '2020-01-10'],
        ]);

        $count = (new EmploymentPeriodConsolidator())->consolidate();

        $this->assertSame(2, $count);
    }

    public function test_clears_previously_consolidated_data_before_running_again(): void
    {
        DB::table('merged_employment_periods')->insert([
            'emp_id' => 999, 'project_id' => 999, 'date_from' => '2000-01-01', 'date_to' => '2000-01-02',
        ]);

        $this->seedRaw([
            ['emp_id' => 1, 'project_id' => 10, 'date_from' => '2020-01-01', 'date_to' => '2020-01-10'],
        ]);

        (new EmploymentPeriodConsolidator())->consolidate();

        $this->assertDatabaseMissing('merged_employment_periods', ['emp_id' => 999]);
        $this->assertSame([
            ['emp_id' => 1, 'project_id' => 10, 'date_from' => '2020-01-01', 'date_to' => '2020-01-10'],
        ], $this->merged());
    }
}
