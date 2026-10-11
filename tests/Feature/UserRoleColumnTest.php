<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * users.role has no default: whoever creates an account has to choose its
 * role, so nothing can quietly hand out karyawan (gate-approving) accounts.
 */
class UserRoleColumnTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_database_refuses_a_user_without_a_role(): void
    {
        $this->expectException(QueryException::class);

        DB::table('users')->insert($this->userRow());
    }

    public function test_a_user_with_an_explicit_role_is_stored(): void
    {
        DB::table('users')->insert($this->userRow() + ['role' => UserRole::Admin->value]);

        $this->assertDatabaseHas('users', ['email' => 'tanpa-default@example.test', 'role' => 'admin']);
    }

    /**
     * @return array<string, string>
     */
    private function userRow(): array
    {
        return [
            'name' => 'Tanpa Default',
            'email' => 'tanpa-default@example.test',
            'password' => 'not-a-real-hash',
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ];
    }
}
