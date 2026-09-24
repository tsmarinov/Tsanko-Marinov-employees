<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class EmployeePairControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_upload_page_loads(): void
    {
        $this->get(route('employee-pairs.show'))
            ->assertStatus(200)
            ->assertSee('Pair of employees who have worked together');
    }

    public function test_it_returns_the_winning_pair_as_json(): void
    {
        $file = UploadedFile::fake()->createWithContent(
            'data.csv',
            "1, 10, 2020-01-01, 2020-01-10\n2, 10, 2020-01-05, 2020-01-20\n"
        );

        $response = $this->post(route('employee-pairs.store'), ['csv' => $file]);

        $response->assertOk()->assertJson([
            'imported' => 2,
            'merged' => 2,
            'winner' => [
                'empA' => 1,
                'empB' => 2,
                'totalDays' => 6,
                'breakdown' => [
                    ['projectId' => 10, 'days' => 6],
                ],
            ],
        ]);
    }

    public function test_it_returns_a_null_winner_when_no_pair_overlaps(): void
    {
        $file = UploadedFile::fake()->createWithContent(
            'data.csv',
            "1, 10, 2020-01-01, 2020-01-10\n2, 20, 2020-01-05, 2020-01-20\n"
        );

        $response = $this->post(route('employee-pairs.store'), ['csv' => $file]);

        $response->assertOk()->assertJson(['winner' => null]);
    }

    public function test_it_requires_a_file(): void
    {
        $this->postJson(route('employee-pairs.store'), [])
            ->assertStatus(422);
    }
}
