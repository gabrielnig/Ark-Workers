<?php

namespace Tests\Unit;

use App\Models\Space;
use App\Models\SpaceAccessGrant;
use App\Models\User;
use App\Policies\SpacePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Exhaustive tests for the space-level restriction model
 * (SECURITY.md §4.2 / ARCHITECTURE.md §4), the single most
 * architecturally important piece of this app. Every branch of
 * SpacePolicy::view() is covered explicitly, by name, rather than
 * relying on a handful of representative cases.
 */
class SpacePolicyTest extends TestCase
{
    use RefreshDatabase;

    private SpacePolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new SpacePolicy;
    }

    // --- Unrestricted spaces: everyone can view, no exceptions ---

    public function test_unrestricted_space_is_viewable_by_ordinary_staff(): void
    {
        $space = Space::factory()->create(['is_restricted' => false]);
        $user = User::factory()->role(User::ROLE_CLEANING_STAFF)->create();

        $this->assertTrue($this->policy->view($user, $space));
    }

    public function test_unrestricted_space_is_viewable_even_without_any_grant(): void
    {
        $space = Space::factory()->create(['is_restricted' => false]);
        $user = User::factory()->role(User::ROLE_SECURITY)->create();

        $this->assertTrue($this->policy->view($user, $space));
    }

    // --- Restricted spaces: role bypass ---

    public function test_restricted_space_is_viewable_by_admin_without_a_grant(): void
    {
        $space = Space::factory()->create(['is_restricted' => true]);
        $admin = User::factory()->role(User::ROLE_ADMIN)->create();

        $this->assertTrue($this->policy->view($admin, $space));
    }

    public function test_restricted_space_is_viewable_by_pastor_without_a_grant(): void
    {
        $space = Space::factory()->create(['is_restricted' => true]);
        $pastor = User::factory()->role(User::ROLE_PASTOR)->create();

        $this->assertTrue($this->policy->view($pastor, $space));
    }

    // --- Restricted spaces: explicit grant ---

    public function test_restricted_space_is_viewable_by_a_cleaning_staff_member_with_an_explicit_grant(): void
    {
        // The concrete scenario from SECURITY.md §4.2: the one cleaning
        // staff member who services the Quarters kitchen, without being
        // promoted to Admin.
        $space = Space::factory()->create(['is_restricted' => true]);
        $admin = User::factory()->role(User::ROLE_ADMIN)->create();
        $cleaner = User::factory()->role(User::ROLE_CLEANING_STAFF)->create();

        SpaceAccessGrant::factory()->create([
            'user_id' => $cleaner->id,
            'space_id' => $space->id,
            'granted_by' => $admin->id,
        ]);

        $this->assertTrue($this->policy->view($cleaner, $space));
    }

    // --- Restricted spaces: the critical negative cases ---

    public function test_restricted_space_is_not_viewable_by_facility_manager_without_a_grant(): void
    {
        // Facility Manager is a privileged role but NOT in the role
        // bypass list, this is the case most likely to be gotten
        // wrong by accidentally treating "privileged" as "unrestricted".
        $space = Space::factory()->create(['is_restricted' => true]);
        $manager = User::factory()->role(User::ROLE_FACILITY_MANAGER)->create();

        $this->assertFalse($this->policy->view($manager, $space));
    }

    public function test_restricted_space_is_not_viewable_by_cleaning_staff_without_a_grant(): void
    {
        $space = Space::factory()->create(['is_restricted' => true]);
        $cleaner = User::factory()->role(User::ROLE_CLEANING_STAFF)->create();

        $this->assertFalse($this->policy->view($cleaner, $space));
    }

    public function test_restricted_space_is_not_viewable_by_maintenance_without_a_grant(): void
    {
        $space = Space::factory()->create(['is_restricted' => true]);
        $tech = User::factory()->role(User::ROLE_MAINTENANCE)->create();

        $this->assertFalse($this->policy->view($tech, $space));
    }

    public function test_restricted_space_is_not_viewable_by_security_without_a_grant(): void
    {
        $space = Space::factory()->create(['is_restricted' => true]);
        $security = User::factory()->role(User::ROLE_SECURITY)->create();

        $this->assertFalse($this->policy->view($security, $space));
    }

    public function test_restricted_space_is_not_viewable_by_driver_without_a_grant(): void
    {
        $space = Space::factory()->create(['is_restricted' => true]);
        $driver = User::factory()->role(User::ROLE_DRIVER)->create();

        $this->assertFalse($this->policy->view($driver, $space));
    }

    // --- Grant scoping: a grant for one space must not leak to another ---

    public function test_a_grant_for_one_restricted_space_does_not_grant_access_to_a_different_restricted_space(): void
    {
        $quarters = Space::factory()->create(['is_restricted' => true]);
        $otherRestrictedSpace = Space::factory()->create(['is_restricted' => true]);
        $admin = User::factory()->role(User::ROLE_ADMIN)->create();
        $cleaner = User::factory()->role(User::ROLE_CLEANING_STAFF)->create();

        SpaceAccessGrant::factory()->create([
            'user_id' => $cleaner->id,
            'space_id' => $quarters->id,
            'granted_by' => $admin->id,
        ]);

        $this->assertTrue($this->policy->view($cleaner, $quarters));
        $this->assertFalse($this->policy->view($cleaner, $otherRestrictedSpace));
    }

    public function test_a_grant_for_a_different_user_does_not_grant_access(): void
    {
        $space = Space::factory()->create(['is_restricted' => true]);
        $admin = User::factory()->role(User::ROLE_ADMIN)->create();
        $granted = User::factory()->role(User::ROLE_CLEANING_STAFF)->create();
        $notGranted = User::factory()->role(User::ROLE_CLEANING_STAFF)->create();

        SpaceAccessGrant::factory()->create([
            'user_id' => $granted->id,
            'space_id' => $space->id,
            'granted_by' => $admin->id,
        ]);

        $this->assertTrue($this->policy->view($granted, $space));
        $this->assertFalse($this->policy->view($notGranted, $space));
    }

    // --- Parent/child space independence ---

    public function test_restriction_on_a_parent_space_does_not_automatically_restrict_an_unrestricted_child(): void
    {
        // The data model allows independent restriction flags per space
        // in the hierarchy, nothing in SECURITY.md §4.2 says the flag
        // inherits down the tree, so the Policy must not assume it does.
        $parent = Space::factory()->create(['is_restricted' => true]);
        $child = Space::factory()->create(['is_restricted' => false, 'parent_space_id' => $parent->id]);
        $cleaner = User::factory()->role(User::ROLE_CLEANING_STAFF)->create();

        $this->assertFalse($this->policy->view($cleaner, $parent));
        $this->assertTrue($this->policy->view($cleaner, $child));
    }

    // --- viewAny: listing is permitted at the policy layer (scoping happens in the query) ---

    public function test_view_any_is_true_for_every_role(): void
    {
        foreach (User::ROLES as $role) {
            $user = User::factory()->role($role)->create();
            $this->assertTrue($this->policy->viewAny($user), "viewAny failed for role: {$role}");
        }
    }

    // --- create/update/delete: role gating independent of restriction flag ---

    public function test_only_admin_pastor_and_facility_manager_can_create_spaces(): void
    {
        $allowed = [User::ROLE_ADMIN, User::ROLE_PASTOR, User::ROLE_FACILITY_MANAGER];

        foreach (User::ROLES as $role) {
            $user = User::factory()->role($role)->create();
            $this->assertSame(
                in_array($role, $allowed, true),
                $this->policy->create($user),
                "create() gave wrong result for role: {$role}"
            );
        }
    }

    public function test_facility_manager_can_update_a_restricted_space_configuration_without_an_explicit_grant(): void
    {
        // Updating the space record itself (e.g. its name) is a
        // configuration action distinct from viewing its contents,
        // Facility Manager can do this even though view() would deny
        // them access to the restricted space's contents.
        $space = Space::factory()->create(['is_restricted' => true]);
        $manager = User::factory()->role(User::ROLE_FACILITY_MANAGER)->create();

        $this->assertTrue($this->policy->update($manager, $space));
    }

    public function test_only_admin_and_pastor_can_delete_a_space(): void
    {
        foreach (User::ROLES as $role) {
            $user = User::factory()->role($role)->create();
            $space = Space::factory()->create();

            $this->assertSame(
                in_array($role, User::UNRESTRICTED_ROLES, true),
                $this->policy->delete($user, $space),
                "delete() gave wrong result for role: {$role}"
            );
        }
    }
}
