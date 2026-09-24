<?php

namespace Tests\Feature\Services;

use App\Services\Employees\EmployeePairFinder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EmployeePairFinderTest extends TestCase
{
    use RefreshDatabase;

    private function seedPeriods(array $rows): void
    {
        foreach ($rows as $row) {
            DB::table('merged_employment_periods')->insert($row);
        }
    }

    public function test_finds_the_winning_pair_on_a_single_shared_project(): void
    {
        $this->seedPeriods([
            ['emp_id' => 1, 'project_id' => 10, 'date_from' => '2020-01-01', 'date_to' => '2020-01-10'],
            ['emp_id' => 2, 'project_id' => 10, 'date_from' => '2020-01-05', 'date_to' => '2020-01-20'],
        ]);

        $result = (new EmployeePairFinder())->findWinningPair();

        $this->assertNotNull($result);
        $this->assertSame(1, $result['empA']);
        $this->assertSame(2, $result['empB']);
        $this->assertSame(6, $result['totalDays']); // 01-05..01-10 inclusive
        $this->assertSame([['projectId' => 10, 'days' => 6]], $result['breakdown']);
    }

    public function test_unions_simultaneous_overlaps_on_different_projects_instead_of_summing(): void
    {
        $this->seedPeriods([
            // project 1 overlap: 01-05..01-10 (6 days)
            ['emp_id' => 1, 'project_id' => 1, 'date_from' => '2020-01-01', 'date_to' => '2020-01-10'],
            ['emp_id' => 2, 'project_id' => 1, 'date_from' => '2020-01-05', 'date_to' => '2020-01-15'],
            // project 2 overlap: 01-08..01-20 (13 days), overlapping project 1's window
            ['emp_id' => 1, 'project_id' => 2, 'date_from' => '2020-01-08', 'date_to' => '2020-01-25'],
            ['emp_id' => 2, 'project_id' => 2, 'date_from' => '2020-01-01', 'date_to' => '2020-01-20'],
        ]);

        $result = (new EmployeePairFinder())->findWinningPair();

        $this->assertNotNull($result);
        // union of [01-05,01-10] and [01-08,01-20] = [01-05,01-20] = 16 days, NOT 6+13=19
        $this->assertSame(16, $result['totalDays']);

        $breakdown = collect($result['breakdown'])->keyBy('projectId');
        $this->assertSame(6, $breakdown[1]['days']);
        $this->assertSame(13, $breakdown[2]['days']);
    }

    public function test_picks_the_pair_with_the_larger_total_over_a_lesser_pair(): void
    {
        $this->seedPeriods([
            // pair (1,2): 2 days overlap
            ['emp_id' => 1, 'project_id' => 1, 'date_from' => '2020-01-01', 'date_to' => '2020-01-02'],
            ['emp_id' => 2, 'project_id' => 1, 'date_from' => '2020-01-01', 'date_to' => '2020-01-02'],
            // pair (3,4): 30 days overlap
            ['emp_id' => 3, 'project_id' => 5, 'date_from' => '2020-05-01', 'date_to' => '2020-05-30'],
            ['emp_id' => 4, 'project_id' => 5, 'date_from' => '2020-05-01', 'date_to' => '2020-05-30'],
        ]);

        $result = (new EmployeePairFinder())->findWinningPair();

        $this->assertSame(3, $result['empA']);
        $this->assertSame(4, $result['empB']);
        $this->assertSame(30, $result['totalDays']);
    }

    public function test_returns_null_when_no_two_employees_ever_overlap(): void
    {
        $this->seedPeriods([
            ['emp_id' => 1, 'project_id' => 1, 'date_from' => '2020-01-01', 'date_to' => '2020-01-10'],
            // emp 2 never shares a project with emp 1 at all
            ['emp_id' => 2, 'project_id' => 2, 'date_from' => '2020-01-01', 'date_to' => '2020-01-10'],
        ]);

        $result = (new EmployeePairFinder())->findWinningPair();

        $this->assertNull($result);
    }

    public function test_unions_two_separate_overlap_windows_on_the_same_project(): void
    {
        $this->seedPeriods([
            // emp 1 has two separate stints on project 1
            ['emp_id' => 1, 'project_id' => 1, 'date_from' => '2020-01-01', 'date_to' => '2020-01-10'],
            ['emp_id' => 1, 'project_id' => 1, 'date_from' => '2020-02-01', 'date_to' => '2020-02-10'],
            // emp 2 spans across both stints, with a real gap between them
            ['emp_id' => 2, 'project_id' => 1, 'date_from' => '2020-01-05', 'date_to' => '2020-02-05'],
        ]);

        $result = (new EmployeePairFinder())->findWinningPair();

        // overlap 1: 01-05..01-10 = 6 days; overlap 2: 02-01..02-05 = 5 days;
        // the two windows don't touch each other, so union total = sum = 11
        $this->assertSame(11, $result['totalDays']);
        $this->assertSame([['projectId' => 1, 'days' => 11]], $result['breakdown']);
    }
}
