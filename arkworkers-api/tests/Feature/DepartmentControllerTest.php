<?php

namespace Tests\Feature;

use App\Models\Department;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DepartmentControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_departments_are_listed_publicly_with_no_auth(): void
    {
        Department::factory()->create(['name' => 'Cleaning']);
        Department::factory()->create(['name' => 'Choir']);

        $this->getJson('/api/departments')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['name' => 'Cleaning'])
            ->assertJsonFragment(['name' => 'Choir']);
    }

    public function test_departments_are_returned_alphabetically(): void
    {
        Department::factory()->create(['name' => 'Ushering']);
        Department::factory()->create(['name' => 'Cleaning']);

        $response = $this->getJson('/api/departments')->assertOk();

        $names = collect($response->json('data'))->pluck('name')->all();
        $this->assertSame(['Cleaning', 'Ushering'], $names);
    }
}
