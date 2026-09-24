<?php

namespace Tests\Feature;

use App\Models\EmploymentPeriodRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImportEmployeesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_errors_when_no_csv_files_are_present(): void
    {
        Storage::fake('csv_input');

        $this->artisan('employees:import')
            ->assertExitCode(1);
    }

    public function test_it_imports_the_chosen_file_and_clears_previous_data(): void
    {
        Storage::fake('csv_input');
        Storage::disk('csv_input')->put(
            'data.csv',
            "143, 12, 2013-11-01, 2014-01-05\n218, 10, 2012-05-16, NULL\n143, 10, NULL, 2011-04-27\n"
        );

        EmploymentPeriodRecord::insert([
            'emp_id' => 999, 'project_id' => 999, 'date_from' => '2000-01-01', 'date_to' => '2000-01-02',
        ]);

        $this->artisan('employees:import')
            ->expectsChoice('Which file do you want to import?', 'data.csv', ['data.csv'])
            ->assertExitCode(0);

        $this->assertDatabaseMissing('employment_periods', ['emp_id' => 999]);
        $this->assertDatabaseHas('employment_periods', ['emp_id' => 143, 'project_id' => 12]);
        // the third row has DateFrom = NULL and must be skipped
        $this->assertSame(2, EmploymentPeriodRecord::count());
    }

    public function test_it_consolidates_and_prints_the_winning_pair(): void
    {
        Storage::fake('csv_input');
        Storage::disk('csv_input')->put(
            'data.csv',
            "1, 10, 2020-01-01, 2020-01-10\n2, 10, 2020-01-05, 2020-01-20\n"
        );

        $this->artisan('employees:import')
            ->expectsChoice('Which file do you want to import?', 'data.csv', ['data.csv'])
            ->expectsOutputToContain('1, 2, 6')
            ->assertExitCode(0);

        $this->assertDatabaseHas('merged_employment_periods', [
            'emp_id' => 1, 'project_id' => 10, 'date_from' => '2020-01-01', 'date_to' => '2020-01-10',
        ]);
    }

    public function test_it_warns_when_no_pair_is_found(): void
    {
        Storage::fake('csv_input');
        Storage::disk('csv_input')->put(
            'data.csv',
            "1, 10, 2020-01-01, 2020-01-10\n2, 20, 2020-01-05, 2020-01-20\n"
        );

        $this->artisan('employees:import')
            ->expectsChoice('Which file do you want to import?', 'data.csv', ['data.csv'])
            ->expectsOutputToContain('No two employees ever worked together')
            ->assertExitCode(0);
    }
}
