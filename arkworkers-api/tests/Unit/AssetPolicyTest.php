<?php

namespace Tests\Unit;

use App\Models\Asset;
use App\Models\Space;
use App\Models\SpaceAccessGrant;
use App\Policies\AssetPolicy;
use App\Policies\SpacePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * AssetPolicy delegates entirely to SpacePolicy for view access, these
 * tests confirm the delegation actually happens (an asset in a
 * restricted space is exactly as protected as the space itself) rather
 * than re-verifying every SpacePolicy branch already covered in
 * SpacePolicyTest.
 */
class AssetPolicyTest extends TestCase
{
    use RefreshDatabase;

    private AssetPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new AssetPolicy(new SpacePolicy);
    }

    public function test_asset_in_unrestricted_space_is_viewable_by_any_role(): void
    {
        $space = Space::factory()->create(['is_restricted' => false]);
        $asset = Asset::factory()->create(['space_id' => $space->id]);
        $cleaner = $this->staffUser();

        $this->assertTrue($this->policy->view($cleaner, $asset));
    }

    public function test_asset_in_restricted_space_is_not_viewable_without_a_grant(): void
    {
        $space = Space::factory()->create(['is_restricted' => true]);
        $asset = Asset::factory()->create(['space_id' => $space->id]);
        $cleaner = $this->staffUser();

        $this->assertFalse($this->policy->view($cleaner, $asset));
    }

    public function test_asset_in_restricted_space_is_viewable_with_an_explicit_grant(): void
    {
        $space = Space::factory()->create(['is_restricted' => true]);
        $asset = Asset::factory()->create(['space_id' => $space->id]);
        $admin = $this->adminUser();
        $cleaner = $this->staffUser();

        SpaceAccessGrant::factory()->create([
            'user_id' => $cleaner->id,
            'space_id' => $space->id,
            'granted_by' => $admin->id,
        ]);

        $this->assertTrue($this->policy->view($cleaner, $asset));
    }

    public function test_asset_in_restricted_space_is_viewable_by_admin_without_a_grant(): void
    {
        $space = Space::factory()->create(['is_restricted' => true]);
        $asset = Asset::factory()->create(['space_id' => $space->id]);
        $admin = $this->adminUser();

        $this->assertTrue($this->policy->view($admin, $asset));
    }

    public function test_a_grant_on_one_space_does_not_expose_an_asset_in_a_different_restricted_space(): void
    {
        // The IDOR-relevant case: grants must be checked per-asset's
        // actual space_id, never assumed from a sibling grant.
        $grantedSpace = Space::factory()->create(['is_restricted' => true]);
        $otherSpace = Space::factory()->create(['is_restricted' => true]);
        $otherAsset = Asset::factory()->create(['space_id' => $otherSpace->id]);
        $admin = $this->adminUser();
        $cleaner = $this->staffUser();

        SpaceAccessGrant::factory()->create([
            'user_id' => $cleaner->id,
            'space_id' => $grantedSpace->id,
            'granted_by' => $admin->id,
        ]);

        $this->assertFalse($this->policy->view($cleaner, $otherAsset));
    }

    public function test_update_requires_both_space_access_and_management_permission(): void
    {
        $space = Space::factory()->create(['is_restricted' => true]);
        $asset = Asset::factory()->create(['space_id' => $space->id]);
        $admin = $this->adminUser();

        // Manager: has management permission, but no grant to this
        // restricted space -> update must still be denied.
        $manager = $this->managerUser();
        $this->assertFalse($this->policy->update($manager, $asset));

        // Admin: bypasses the space restriction and has the permission.
        $this->assertTrue($this->policy->update($admin, $asset));
    }

    public function test_only_admin_can_delete_an_asset(): void
    {
        $space = Space::factory()->create(['is_restricted' => false]);
        $asset = Asset::factory()->create(['space_id' => $space->id]);

        $this->assertTrue($this->policy->delete($this->adminUser(), $asset));
        $this->assertFalse($this->policy->delete($this->managerUser(), $asset));
        $this->assertFalse($this->policy->delete($this->pastorUser(), $asset));
        $this->assertFalse($this->policy->delete($this->staffUser(), $asset));
    }
}
