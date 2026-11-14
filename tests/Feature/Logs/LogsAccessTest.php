<?php

namespace Tests\Feature\Logs;

use App\Models\Role;
use App\Models\User;
use Tests\RefreshDatabase;
use Tests\TestCase;

class LogsAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/logs')->assertRedirect(route('login'));
    }

    public function test_user_without_role_gets_403(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/logs')->assertForbidden();
    }

    public function test_standard_user_gets_403(): void
    {
        $user = User::factory()->create([
            'role_id' => Role::where('slug', Role::USER)->value('id'),
        ]);

        $this->actingAs($user)->get('/logs')->assertForbidden();
        $this->actingAs($user)->get('/logs/data')->assertForbidden();
    }

    public function test_admin_can_view_logs(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/logs')->assertOk();
    }

    public function test_user_can_be_assigned_and_unassigned_role(): void
    {
        $user = User::factory()->create();
        $this->assertFalse($user->isAdmin());

        $user->role()->associate(Role::where('slug', Role::ADMIN)->first())->save();
        $this->assertTrue($user->fresh()->isAdmin());

        $user->role()->dissociate()->save();
        $this->assertFalse($user->fresh()->isAdmin());
    }

    public function test_deleting_a_role_keeps_its_users(): void
    {
        $role = Role::create(['name' => 'Temporal', 'slug' => 'temporal']);
        $user = User::factory()->create(['role_id' => $role->id]);

        $role->delete();

        $this->assertNotNull(User::find($user->id));
        $this->assertNull($user->fresh()->role_id);
    }

    public function test_set_role_command(): void
    {
        $user = User::factory()->create(['email' => 'ana@example.test']);

        $this->artisan('users:set-role', ['email' => 'ana@example.test', 'role' => 'admin'])->assertSuccessful();
        $this->assertTrue($user->fresh()->isAdmin());

        $this->artisan('users:set-role', ['email' => 'ana@example.test'])->assertSuccessful();
        $this->assertNull($user->fresh()->role_id);
    }
}
