<?php

declare(strict_types=1);

namespace Zairakai\LaravelActivity\Tests\Feature;

use DB;
use Illuminate\Auth\Authenticatable as AuthenticatableTrait;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Activitylog\Models\Activity;
use Zairakai\LaravelActivity\Tests\TestCase;
use Zairakai\LaravelActivity\Traits\TraceActivities;

final class FluentApiTest extends TestCase
{
    private Role $adminRole;

    private Role $editorRole;

    private User $user;

    private Role $viewerRole;

    protected function setUp(): void
    {
        parent::setUp();

        // Drop tables if they exist
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('users');

        // Create users table
        Schema::create('users', function (Blueprint $blueprint): void {
            $blueprint->id();
            $blueprint->string('name');
            $blueprint->timestamps();
        });

        // Create roles table
        Schema::create('roles', function (Blueprint $blueprint): void {
            $blueprint->id();
            $blueprint->string('name');
            $blueprint->timestamps();
        });

        // Create pivot table (no id column - use composite primary key)
        Schema::create('role_user', function (Blueprint $blueprint): void {
            $blueprint->foreignId('user_id')->constrained()->cascadeOnDelete();
            $blueprint->foreignId('role_id')->constrained()->cascadeOnDelete();
            $blueprint->timestamp('granted_at')->nullable();
            $blueprint->string('granted_by')->nullable();
            $blueprint->timestamps();

            $blueprint->primary(['user_id', 'role_id']);
        });

        // Create test data
        $this->user       = User::create(['name' => 'John Doe']);
        $this->adminRole  = Role::create(['name' => 'admin']);
        $this->editorRole = Role::create(['name' => 'editor']);
        $this->viewerRole = Role::create(['name' => 'viewer']);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('users');

        parent::tearDown();
    }

    #[Test]
    public function it_can_attach_with_custom_causer(): void
    {
        $admin = User::create(['name' => 'Admin User']);

        $this->user->roles()
            ->activity()
            ->by($admin)
            ->attach([$this->adminRole->id]);

        $activity = Activity::first();
        $this->assertEquals($admin->id, $activity->causer_id);
        $this->assertEquals(User::class, $activity->causer_type);
    }

    #[Test]
    public function it_can_attach_with_custom_message(): void
    {
        $this->user->roles()
            ->activity()
            ->withMessage('Assigned admin and editor roles')
            ->attach([$this->adminRole->id, $this->editorRole->id]);

        $activity = Activity::first();
        $this->assertEquals('Assigned admin and editor roles', $activity->description);
    }

    #[Test]
    public function it_can_attach_with_fluent_api(): void
    {
        $this->user->roles()
            ->activity()
            ->attach([$this->adminRole->id, $this->editorRole->id]);

        $this->assertDatabaseHas('role_user', [
            'user_id' => $this->user->id,
            'role_id' => $this->adminRole->id,
        ]);

        $this->assertDatabaseHas('role_user', [
            'user_id' => $this->user->id,
            'role_id' => $this->editorRole->id,
        ]);

        // Verify activity was logged
        $this->assertEquals(1, Activity::count());

        $activity = Activity::first();
        $this->assertEquals('attached', $activity->properties['operation']);
        $this->assertEquals('roles', $activity->properties['relation']);
        $this->assertContains($this->adminRole->id, $activity->properties['attributes']);
        $this->assertContains($this->editorRole->id, $activity->properties['attributes']);
    }

    #[Test]
    public function it_can_detach_with_fluent_api(): void
    {
        // Setup: attach roles first
        $this->user->roles()->attach([$this->adminRole->id, $this->editorRole->id]);

        // Detach one role
        $this->user->roles()
            ->activity()
            ->detach([$this->adminRole->id]);

        $this->assertDatabaseMissing('role_user', [
            'user_id' => $this->user->id,
            'role_id' => $this->adminRole->id,
        ]);

        $this->assertDatabaseHas('role_user', [
            'user_id' => $this->user->id,
            'role_id' => $this->editorRole->id,
        ]);

        // Verify activity logged
        $activity = Activity::first();
        $this->assertEquals('detached', $activity->properties['operation']);
        $this->assertNotContains($this->adminRole->id, $activity->properties['attributes']);
    }

    #[Test]
    public function it_can_query_activities_via_trace_activities_trait(): void
    {
        // Attach without explicit causer — parent model (user) becomes the causer by default
        $this->user->roles()
            ->activity()
            ->attach([$this->adminRole->id]);

        // User is the subject (performed ON)
        $onActivities = $this->user->activities()->on()->get();
        $this->assertCount(1, $onActivities);
        $this->assertEquals('attached', $onActivities->first()->properties['operation']);

        // User is also the default causer (caused BY), so by() also returns 1
        $byActivities = $this->user->activities()->by()->get();
        $this->assertCount(1, $byActivities);
    }

    #[Test]
    public function it_can_sync_with_fluent_api(): void
    {
        // Setup: attach initial roles
        $this->user->roles()->attach([$this->adminRole->id, $this->editorRole->id]);

        // Sync to different roles
        $this->user->roles()
            ->activity()
            ->sync([$this->editorRole->id, $this->viewerRole->id]);

        // Should have viewer and editor, but not admin
        $roleIds = $this->user->roles()->pluck('id')->toArray();
        $this->assertContains($this->editorRole->id, $roleIds);
        $this->assertContains($this->viewerRole->id, $roleIds);
        $this->assertNotContains($this->adminRole->id, $roleIds);

        // Verify activity logged
        $activity = Activity::first();
        $this->assertEquals('synced', $activity->properties['operation']);
    }

    #[Test]
    public function it_can_sync_without_detaching(): void
    {
        // Setup
        $this->user->roles()->attach([$this->adminRole->id]);

        // Sync without detaching
        $this->user->roles()
            ->activity()
            ->syncWithoutDetaching([$this->editorRole->id, $this->viewerRole->id]);

        // Should have all three roles
        $this->assertEquals(3, $this->user->roles()->count());
        $roleIds = $this->user->roles()->pluck('id')->toArray();
        $this->assertContains($this->adminRole->id, $roleIds);
        $this->assertContains($this->editorRole->id, $roleIds);
        $this->assertContains($this->viewerRole->id, $roleIds);

        // Verify activity logged
        $activity = Activity::first();
        $this->assertEquals('synced_without_detaching', $activity->properties['operation']);
    }

    #[Test]
    public function it_can_toggle_with_fluent_api(): void
    {
        // Setup: attach admin role
        $this->user->roles()->attach($this->adminRole->id);

        // Toggle admin (detach) and editor (attach)
        $this->user->roles()
            ->activity()
            ->toggle([$this->adminRole->id, $this->editorRole->id]);

        // Admin should be removed, editor added
        $roleIds = $this->user->roles()->pluck('id')->toArray();
        $this->assertNotContains($this->adminRole->id, $roleIds);
        $this->assertContains($this->editorRole->id, $roleIds);

        // Verify activity logged
        $activity = Activity::first();
        $this->assertEquals('toggled', $activity->properties['operation']);
    }

    #[Test]
    public function it_can_update_existing_pivot(): void
    {
        // Setup: attach with pivot attributes
        $oldDate = now()->subDays(7);
        $this->user->roles()->attach($this->adminRole->id, [
            'granted_at' => $oldDate,
            'granted_by' => 'System',
        ]);

        // Update pivot attributes
        $newDate = now();
        $count   = $this->user->roles()
            ->activity()
            ->updateExistingPivot($this->adminRole->id, [
                'granted_at' => $newDate,
                'granted_by' => 'Admin User',
            ]);

        $this->assertEquals(1, $count);

        // Verify pivot updated in database
        $pivotRecord = DB::table('role_user')
            ->where('user_id', $this->user->id)
            ->where('role_id', $this->adminRole->id)
            ->first();

        $this->assertEquals('Admin User', $pivotRecord->granted_by);
        $this->assertNotNull($pivotRecord->granted_at);

        // Verify activity logged
        $activity = Activity::first();
        $this->assertEquals('updated_pivot', $activity->properties['operation']);
        $this->assertArrayHasKey($this->adminRole->id, $activity->properties['attributes']);
    }

    #[Test]
    public function it_chains_fluent_methods_correctly(): void
    {
        $admin = User::create(['name' => 'Admin']);

        $this->user->roles()
            ->activity()
            ->by($admin)
            ->withMessage('Custom operation')
            ->attach([$this->adminRole->id]);

        $activity = Activity::first();
        $this->assertEquals($admin->id, $activity->causer_id);
        $this->assertEquals('Custom operation', $activity->description);
        $this->assertEquals($this->user->id, $activity->subject_id);
    }

    #[Test]
    public function it_does_not_attach_existing_ids(): void
    {
        // Attach first time
        $this->user->roles()->attach($this->adminRole->id);

        // Try to attach again with fluent API
        $this->user->roles()
            ->activity()
            ->attach([$this->adminRole->id]);

        // Should only have 1 record (not duplicated)
        $this->assertEquals(1, $this->user->roles()->count());

        // Should not create activity log (no change)
        $this->assertEquals(0, Activity::count());
    }

    #[Test]
    public function it_does_not_log_sync_when_nothing_changed(): void
    {
        // Setup
        $this->user->roles()->attach([$this->adminRole->id, $this->editorRole->id]);

        // Sync same IDs
        $this->user->roles()
            ->activity()
            ->sync([$this->adminRole->id, $this->editorRole->id]);

        // Should not create activity (no change)
        $this->assertEquals(0, Activity::count());
    }

    #[Test]
    public function it_logs_activity_with_anonymous_causer(): void
    {
        $this->user->roles()
            ->activity()
            ->byAnonymous()
            ->attach([$this->adminRole->id]);

        $activity = Activity::first();
        $this->assertNull($activity->causer_id);
        $this->assertNull($activity->causer_type);
        $this->assertEquals('attached', $activity->properties['operation']);
    }

    #[Test]
    public function it_resolves_auth_user_as_causer_when_by_called_without_argument(): void
    {
        $admin = User::create(['name' => 'Auth User']);

        // Set the authenticated user directly on the guard so that Auth::user()
        // executes real code (lines traced by PCOV) instead of a Mockery intercept.
        Auth::guard()->setUser($admin);

        $this->user->roles()
            ->activity()
            ->by()
            ->attach([$this->adminRole->id]);

        $activity = Activity::first();
        $this->assertEquals($admin->id, $activity->causer_id);
    }

    #[Test]
    public function it_skips_detach_when_no_ids_match_existing(): void
    {
        // No roles attached — nothing to detach
        $count = $this->user->roles()
            ->activity()
            ->detach([$this->adminRole->id]);

        $this->assertEquals(0, $count);

        // No activity logged when nothing changed
        $this->assertEquals(0, Activity::count());
    }

    #[Test]
    public function it_skips_sync_without_detaching_when_all_ids_already_present(): void
    {
        // Attach first
        $this->user->roles()->attach([$this->adminRole->id, $this->editorRole->id]);

        // Try to syncWithoutDetaching with already-present IDs
        $result = $this->user->roles()
            ->activity()
            ->syncWithoutDetaching([$this->adminRole->id, $this->editorRole->id]);

        $this->assertSame(['attached' => [], 'detached' => [], 'updated' => []], $result);

        // No activity logged
        $this->assertEquals(0, Activity::count());
    }

    #[Test]
    public function it_works_with_collections(): void
    {
        $roles = collect([$this->adminRole, $this->editorRole]);

        $this->user->roles()
            ->activity()
            ->attach($roles);

        $this->assertEquals(2, $this->user->roles()->count());
    }

    #[Test]
    public function it_works_with_model_instances_instead_of_ids(): void
    {
        $this->user->roles()
            ->activity()
            ->attach($this->adminRole);

        $this->assertEquals(1, $this->user->roles()->count());

        $activity = Activity::first();
        $this->assertContains($this->adminRole->id, $activity->properties['attributes']);
    }
}

/**
 * Test User model.
 */
class User extends Model implements Authenticatable
{
    use AuthenticatableTrait;
    use TraceActivities;

    protected $guarded = [];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }
}

/**
 * Test Role model.
 */
class Role extends Model
{
    protected $guarded = [];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }
}
