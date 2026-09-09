<?php

namespace Tests\Unit;

use App\Models\Space;
use App\Models\SpaceAccessGrant;
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
        $user = $this->staffUser();

        $this->assertTrue($this->policy->view($user, $space));
    }

    public function test_unrestricted_space_is_viewable_even_without_any_grant(): void
    {
        $space = Space::factory()->create(['is_restricted' => false]);
        $user = $this->staffUser();

        $this->assertTrue($this->policy->view($user, $space));
    }

    // --- Restricted spaces: bypass is Admin-only ---

    public function test_restricted_space_is_viewable_by_admin_without_a_grant(): void
    {
        $space = Space::factory()->create(['is_restricted' => true]);
        $admin = $this->adminUser();

        $this->assertTrue($this->policy->view($admin, $space));
    }

    public function test_restricted_space_is_not_viewable_by_pastor_without_a_grant(): void
    {
        // Pastor is a title only, per this session's decision it
        // carries zero permission weight, this is the case most
        // likely to regress back toward the old admin/pastor bypass.
        $space = Space::factory()->create(['is_restricted' => true]);
        $pastor = $this->pastorUser();

        $this->assertFalse($this->policy->view($pastor, $space));
    }

    // --- Restricted spaces: explicit grant ---

    public function test_restricted_space_is_viewable_by_a_cleaning_staff_member_with_an_explicit_grant(): void
    {
        // The concrete scenario from SECURITY.md §4.2: the one cleaning
        // staff member who services the Quarters kitchen, without being
        // promoted to Admin.
        $space = Space::factory()->create(['is_restricted' => true]);
        $admin = $this->adminUser();
        $cleaner = $this->staffUser();

        SpaceAccessGrant::factory()->create([
            'user_id' => $cleaner->id,
            'space_id' => $space->id,
            'granted_by' => $admin->id,
        ]);

        $this->assertTrue($this->policy->view($cleaner, $space));
    }

    // --- Restricted spaces: the critical negative cases ---

    public function test_restricted_space_is_not_viewable_by_a_manager_without_a_grant(): void
    {
        // A department role with grants_management is privileged for
        // management actions but NOT a view bypass, this is the case
        // most likely to be gotten wrong by conflating the two.
        $space = Space::factory()->create(['is_restricted' => true]);
        $manager = $this->managerUser();

        $this->assertFalse($this->policy->view($manager, $space));
    }

    public function test_restricted_space_is_not_viewable_by_ordinary_staff_without_a_grant(): void
    {
        $space = Space::factory()->create(['is_restricted' => true]);
        $cleaner = $this->staffUser();

        $this->assertFalse($this->policy->view($cleaner, $space));
    }

    // --- Grant scoping: a grant for one space must not leak to another ---

    public function test_a_grant_for_one_restricted_space_does_not_grant_access_to_a_different_restricted_space(): void
    {
        $quarters = Space::factory()->create(['is_restricted' => true]);
        $otherRestrictedSpace = Space::factory()->create(['is_restricted' => true]);
        $admin = $this->adminUser();
        $cleaner = $this->staffUser();

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
        $admin = $this->adminUser();
        $granted = $this->staffUser();
        $notGranted = $this->staffUser();

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
        $cleaner = $this->staffUser();

        $this->assertFalse($this->policy->view($cleaner, $parent));
        $this->assertTrue($this->policy->view($cleaner, $child));
    }

    // --- viewAny: listing is permitted at the policy layer (scoping happens in the query) ---

    public function test_view_any_is_true_regardless_of_permission_level(): void
    {
        $this->assertTrue($this->policy->viewAny($this->staffUser()));
        $this->assertTrue($this->policy->viewAny($this->adminUser()));
        $this->assertTrue($this->policy->viewAny($this->managerUser()));
        $this->assertTrue($this->policy->viewAny($this->pastorUser()));
    }

    // --- create/update/delete: management permission gating, independent of restriction flag ---

    public function test_admin_can_create_spaces(): void
    {
        $this->assertTrue($this->policy->create($this->adminUser()));
    }

    public function test_a_manager_can_create_spaces(): void
    {
        $this->assertTrue($this->policy->create($this->managerUser()));
    }

    public function test_pastor_cannot_create_spaces(): void
    {
        $this->assertFalse($this->policy->create($this->pastorUser()));
    }

    public function test_ordinary_staff_cannot_create_spaces(): void
    {
        $this->assertFalse($this->policy->create($this->staffUser()));
    }

    public function test_a_manager_can_update_a_restricted_space_configuration_without_an_explicit_grant(): void
    {
        // Updating the space record itself (e.g. its name) is a
        // configuration action distinct from viewing its contents, a
        // manager can do this even though view() would deny them
        // access to the restricted space's contents.
        $space = Space::factory()->create(['is_restricted' => true]);
        $manager = $this->managerUser();

        $this->assertTrue($this->policy->update($manager, $space));
    }

    public function test_only_admin_can_delete_a_space(): void
    {
        $space = Space::factory()->create();

        $this->assertTrue($this->policy->delete($this->adminUser(), $space));
        $this->assertFalse($this->policy->delete($this->managerUser(), $space));
        $this->assertFalse($this->policy->delete($this->pastorUser(), $space));
        $this->assertFalse($this->policy->delete($this->staffUser(), $space));
    }
}
