<?php

declare(strict_types=1);

namespace Zairakai\LaravelActivity\Tests\Feature;

use Illuminate\Auth\Authenticatable as AuthenticatableTrait;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Support\ActivityLogStatus;
use Zairakai\LaravelActivity\Support\PivotActivityFluent;
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
        foreach (['taggables', 'tags', 'posts', 'role_user', 'roles', 'users'] as $table) {
            Schema::dropIfExists($table);
        }

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
            $blueprint->string('token')->nullable();
            $blueprint->timestamps();

            $blueprint->primary(['user_id', 'role_id']);
        });

        Schema::create('posts', function (Blueprint $blueprint): void {
            $blueprint->id();
            $blueprint->string('title');
        });

        Schema::create('tags', function (Blueprint $blueprint): void {
            $blueprint->id();
            $blueprint->string('name');
        });

        Schema::create('taggables', function (Blueprint $blueprint): void {
            $blueprint->foreignId('tag_id');
            $blueprint->morphs('taggable');
            $blueprint->string('label')->nullable();
        });

        // Create test data
        $this->user       = User::query()->create(['name' => 'John Doe']);
        $this->adminRole  = Role::query()->create(['name' => 'admin']);
        $this->editorRole = Role::query()->create(['name' => 'editor']);
        $this->viewerRole = Role::query()->create(['name' => 'viewer']);
    }

    protected function tearDown(): void
    {
        foreach (['taggables', 'tags', 'posts', 'role_user', 'roles', 'users'] as $table) {
            Schema::dropIfExists($table);
        }

        parent::tearDown();
    }

    #[Test]
    public function it_applies_a_message_to_one_operation_only(): void
    {
        $fluent = $this->pivotActivity()->withMessage('Once');

        // Nothing changes here, so nothing is logged and the message is spent
        $fluent->detach($this->adminRole);
        $fluent->attach($this->adminRole);

        self::assertSame('Attached 1 role', $this->lastActivity()->description);
    }

    #[Test]
    public function it_attaches_with_pivot_attributes_per_id(): void
    {
        $this->pivotActivity()->attach([$this->adminRole->id => ['granted_by' => 'System']]);

        $row = $this->rowOf($this->listProperty($this->lastActivity(), 'attributes'), $this->adminRole->id);

        self::assertSame('System', $row['granted_by']);
    }

    #[Test]
    public function it_can_attach_with_custom_causer(): void
    {
        $admin = User::query()->create(['name' => 'Admin User']);

        $this->pivotActivity()
            ->by($admin)
            ->attach([$this->adminRole->id]);

        $activity = $this->lastActivity();
        self::assertEquals($admin->id, $activity->causer_id);
        self::assertEquals(User::class, $activity->causer_type);
    }

    #[Test]
    public function it_can_attach_with_custom_message(): void
    {
        $this->pivotActivity()
            ->withMessage('Assigned admin and editor roles')
            ->attach([$this->adminRole->id, $this->editorRole->id]);

        $activity = $this->lastActivity();
        self::assertEquals('Assigned admin and editor roles', $activity->description);
    }

    #[Test]
    public function it_can_attach_with_fluent_api(): void
    {
        $this->pivotActivity()
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
        self::assertEquals(1, Activity::query()->toBase()->count());

        $activity = $this->lastActivity();
        self::assertEquals('attached', $activity->event);
        self::assertEquals('roles', $this->properties($activity)['relation']);
        self::assertArrayHasKey($this->adminRole->id, $this->listProperty($activity, 'attributes'));
        self::assertArrayHasKey($this->editorRole->id, $this->listProperty($activity, 'attributes'));
    }

    #[Test]
    public function it_can_detach_with_fluent_api(): void
    {
        // Setup: attach roles first
        $this->user->roles()->attach([$this->adminRole->id, $this->editorRole->id]);

        // Detach one role
        $this->pivotActivity()
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
        $activity = $this->lastActivity();
        self::assertEquals('detached', $activity->event);
        self::assertNotContains($this->adminRole->id, $this->listProperty($activity, 'attributes'));
    }

    #[Test]
    public function it_can_query_activities_via_trace_activities_trait(): void
    {
        Auth::guard()->setUser($this->user);

        $this->pivotActivity()
            ->attach([$this->adminRole->id]);

        // User is the subject (performed ON)
        $onActivities = $this->user->activities()->on()->get();
        self::assertCount(1, $onActivities);
        self::assertEquals('attached', $onActivities->firstOrFail()->event);

        // User is also the causer (caused BY), as the authenticated user
        self::assertCount(1, $this->user->activities()->by()->get());
    }

    #[Test]
    public function it_can_sync_with_fluent_api(): void
    {
        // Setup: attach initial roles
        $this->user->roles()->attach([$this->adminRole->id, $this->editorRole->id]);

        // Sync to different roles
        $this->pivotActivity()
            ->sync([$this->editorRole->id, $this->viewerRole->id]);

        // Should have viewer and editor, but not admin
        $roleIds = $this->roleIds();
        self::assertContains($this->editorRole->id, $roleIds);
        self::assertContains($this->viewerRole->id, $roleIds);
        self::assertNotContains($this->adminRole->id, $roleIds);

        // Verify activity logged
        $activity = $this->lastActivity();
        self::assertEquals('synced', $activity->event);
    }

    #[Test]
    public function it_can_sync_without_detaching(): void
    {
        // Setup
        $this->user->roles()->attach([$this->adminRole->id]);

        // Sync without detaching
        $this->pivotActivity()
            ->syncWithoutDetaching([$this->editorRole->id, $this->viewerRole->id]);

        // Should have all three roles
        self::assertEquals(3, $this->user->roles()->get()->count());
        $roleIds = $this->roleIds();
        self::assertContains($this->adminRole->id, $roleIds);
        self::assertContains($this->editorRole->id, $roleIds);
        self::assertContains($this->viewerRole->id, $roleIds);

        // Verify activity logged
        $activity = $this->lastActivity();
        self::assertEquals('synced_without_detaching', $activity->event);
    }

    #[Test]
    public function it_can_toggle_with_fluent_api(): void
    {
        // Setup: attach admin role
        $this->user->roles()->attach($this->adminRole->id);

        // Toggle admin (detach) and editor (attach)
        $this->pivotActivity()
            ->toggle([$this->adminRole->id, $this->editorRole->id]);

        // Admin should be removed, editor added
        $roleIds = $this->roleIds();
        self::assertNotContains($this->adminRole->id, $roleIds);
        self::assertContains($this->editorRole->id, $roleIds);

        // Verify activity logged
        $activity = $this->lastActivity();
        self::assertEquals('toggled', $activity->event);
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
        $count   = $this->pivotActivity()
            ->updateExistingPivot($this->adminRole->id, [
                'granted_at' => $newDate,
                'granted_by' => 'Admin User',
            ]);

        self::assertEquals(1, $count);

        // Verify pivot updated in database
        $pivotRecord = (array) DB::table('role_user')
            ->where('user_id', $this->user->id)
            ->where('role_id', $this->adminRole->id)
            ->first();

        self::assertSame('Admin User', $pivotRecord['granted_by']);
        self::assertNotNull($pivotRecord['granted_at']);

        // Verify activity logged
        $activity = $this->lastActivity();
        self::assertEquals('updated_pivot', $activity->event);
        self::assertArrayHasKey($this->adminRole->id, $this->listProperty($activity, 'attributes'));
    }

    #[Test]
    public function it_chains_fluent_methods_correctly(): void
    {
        $admin = User::query()->create(['name' => 'Admin']);

        $this->pivotActivity()
            ->by($admin)
            ->withMessage('Custom operation')
            ->attach([$this->adminRole->id]);

        $activity = $this->lastActivity();
        self::assertEquals($admin->id, $activity->causer_id);
        self::assertEquals('Custom operation', $activity->description);
        self::assertEquals($this->user->id, $activity->subject_id);
    }

    #[Test]
    public function it_does_not_attach_existing_ids(): void
    {
        // Attach first time
        $this->user->roles()->attach($this->adminRole->id);

        // Try to attach again with fluent API
        $this->pivotActivity()
            ->attach([$this->adminRole->id]);

        // Should only have 1 record (not duplicated)
        self::assertEquals(1, $this->user->roles()->get()->count());

        // Should not create activity log (no change)
        self::assertEquals(0, Activity::query()->toBase()->count());
    }

    #[Test]
    public function it_does_not_log_a_change_of_a_hidden_column_only(): void
    {
        $this->user->roles()->attach($this->adminRole->id, ['token' => 'a']);

        $this->pivotActivity()->updateExistingPivot($this->adminRole->id, ['token' => 'b']);

        self::assertSame(0, Activity::query()->toBase()->count());
    }

    #[Test]
    public function it_does_not_log_a_sync_without_detaching_that_changes_nothing(): void
    {
        $this->user->roles()->attach([$this->adminRole->id, $this->editorRole->id]);

        $this->pivotActivity()->sync([$this->adminRole->id], false);

        self::assertSame(0, Activity::query()->toBase()->count());
    }

    #[Test]
    public function it_does_not_log_a_toggle_without_ids(): void
    {
        $this->pivotActivity()->toggle([]);

        self::assertSame(0, Activity::query()->toBase()->count());
    }

    #[Test]
    public function it_does_not_log_sync_when_nothing_changed(): void
    {
        // Setup
        $this->user->roles()->attach([$this->adminRole->id, $this->editorRole->id]);

        // Sync same IDs
        $this->pivotActivity()
            ->sync([$this->adminRole->id, $this->editorRole->id]);

        // Should not create activity (no change)
        self::assertEquals(0, Activity::query()->toBase()->count());
    }

    #[Test]
    public function it_finds_the_activities_where_a_model_is_on_the_other_side(): void
    {
        $this->pivotActivity()->attach($this->adminRole);
        $this->pivotActivity()->attach($this->editorRole);
        $this->pivotActivity()->detach($this->adminRole);

        self::assertCount(2, $this->adminRole->activities()->related()->get());
        self::assertCount(1, $this->editorRole->activities()->related()->get());
        self::assertCount(0, $this->viewerRole->activities()->related()->get());
    }

    #[Test]
    public function it_follows_the_hidden_columns_of_the_configuration(): void
    {
        config()->set('laravel-activity.hidden_columns', ['granted_by']);

        $this->pivotActivity()->attach($this->adminRole, ['granted_by' => 'System', 'token' => 'abc']);

        $row = $this->rowOf($this->listProperty($this->lastActivity(), 'attributes'), $this->adminRole->id);

        self::assertArrayNotHasKey('granted_by', $row);
        self::assertSame('abc', $row['token']);
    }

    #[Test]
    public function it_goes_back_to_the_causer_of_spatie_with_by_without_argument(): void
    {
        $admin = User::query()->create(['name' => 'Admin']);
        Auth::guard()->setUser($this->user);

        $this->pivotActivity()->by($admin)->by()->attach($this->adminRole);

        self::assertSame($this->user->id, $this->lastActivity()->causer_id);
    }

    #[Test]
    public function it_has_no_causer_when_nobody_is_authenticated(): void
    {
        $this->pivotActivity()->attach($this->adminRole);

        self::assertNull($this->lastActivity()->causer_id);
        self::assertCount(0, $this->user->activities()->by()->get());
    }

    #[Test]
    public function it_has_the_names_of_spatie(): void
    {
        $admin = User::query()->create(['name' => 'Admin']);

        $this->pivotActivity()->causedBy($admin)->useLog('custom')->attach($this->adminRole);

        self::assertSame($admin->id, $this->lastActivity()->causer_id);
        self::assertSame('custom', $this->lastActivity()->log_name);

        $this->pivotActivity()->causedByAnonymous()->attach($this->editorRole);

        self::assertNull(Activity::query()->latest('id')->firstOrFail()->causer_id);
    }

    #[Test]
    public function it_is_conditionable(): void
    {
        $this->pivotActivity()->when(true, fn ($fluent) => $fluent->withMessage('Conditioned'))->attach($this->adminRole);

        self::assertSame('Conditioned', $this->lastActivity()->description);
    }

    #[Test]
    public function it_keeps_the_anonymous_causer_for_the_whole_instance(): void
    {
        $admin = User::query()->create(['name' => 'Admin']);
        Auth::guard()->setUser($admin);

        $fluent = $this->pivotActivity()->byAnonymous();

        $fluent->attach($this->adminRole);
        $fluent->attach($this->editorRole);

        self::assertSame([null, null], Activity::query()->pluck('causer_id')->all());
    }

    #[Test]
    public function it_keeps_the_causer_for_the_whole_instance(): void
    {
        $admin  = User::query()->create(['name' => 'Admin']);
        $fluent = $this->pivotActivity()->by($admin);

        $fluent->attach($this->adminRole);
        $fluent->attach($this->editorRole);

        self::assertSame(
            [$admin->id, $admin->id],
            Activity::query()->pluck('causer_id')->all(),
        );
    }

    #[Test]
    public function it_keeps_the_hidden_columns_of_the_pivot_model_when_told_to(): void
    {
        config()->set('laravel-activity.use_pivot_hidden', false);

        /** @var PivotActivityFluent<Role, User> $fluent */
        // @phpstan-ignore method.notFound
        $fluent = $this->user->hiddenRoles()->activity();
        $fluent->attach($this->adminRole, ['granted_by' => 'System']);

        $row = $this->rowOf($this->listProperty($this->lastActivity(), 'attributes'), $this->adminRole->id);

        self::assertSame('System', $row['granted_by']);
    }

    #[Test]
    public function it_keeps_the_pivot_row_of_a_detached_role(): void
    {
        $this->user->roles()->attach($this->adminRole->id, ['granted_by' => 'System']);

        $this->pivotActivity()->detach($this->adminRole);

        $properties = $this->properties($this->lastActivity());

        self::assertSame('detached', $this->lastActivity()->event);
        self::assertSame('roles', $properties['relation']);
        self::assertSame('role_user', $properties['table']);
        self::assertSame(Role::class, $properties['related_type']);
        self::assertSame([], $this->listProperty($this->lastActivity(), 'attributes'));

        $old = $this->listProperty($this->lastActivity(), 'old');

        self::assertSame($this->user->id, $this->rowOf($old, $this->adminRole->id)['user_id']);
        self::assertSame('System', $this->rowOf($old, $this->adminRole->id)['granted_by']);
    }

    #[Test]
    public function it_keeps_the_rows_before_and_after_an_update(): void
    {
        $this->user->roles()->attach($this->adminRole->id, ['granted_by' => 'System']);

        $this->pivotActivity()->updateExistingPivot($this->adminRole->id, ['granted_by' => 'Admin']);

        $activity = $this->lastActivity();

        self::assertSame('System', $this->rowOf($this->listProperty($activity, 'old'), $this->adminRole->id)['granted_by']);
        self::assertSame('Admin', $this->rowOf($this->listProperty($activity, 'attributes'), $this->adminRole->id)['granted_by']);
    }

    #[Test]
    public function it_leaves_out_the_columns_of_a_single_call(): void
    {
        $this->pivotActivity()
            ->withoutColumns(['granted_by'])
            ->attach($this->adminRole, ['granted_by' => 'System']);

        $row = $this->rowOf($this->listProperty($this->lastActivity(), 'attributes'), $this->adminRole->id);

        self::assertArrayNotHasKey('granted_by', $row);
    }

    #[Test]
    public function it_leaves_out_the_hidden_columns_of_the_pivot_model(): void
    {
        $this->user->hiddenRoles()->attach($this->adminRole->id, ['granted_by' => 'System']);
        $this->user->hiddenRoles()->detach();

        $this->user->hiddenRoles()->attach($this->adminRole->id, ['granted_by' => 'System']);

        /** @var PivotActivityFluent<Role, User> $fluent */
        // @phpstan-ignore method.notFound
        $fluent = $this->user->hiddenRoles()->activity();
        $fluent->detach();

        $row = $this->rowOf($this->listProperty($this->lastActivity(), 'old'), $this->adminRole->id);

        self::assertArrayNotHasKey('granted_by', $row);
        self::assertArrayHasKey('role_id', $row);
    }

    #[Test]
    public function it_leaves_the_sensitive_columns_out_by_default(): void
    {
        $this->pivotActivity()->attach($this->adminRole, ['granted_by' => 'System', 'token' => 'abc']);

        $row = $this->rowOf($this->listProperty($this->lastActivity(), 'attributes'), $this->adminRole->id);

        self::assertSame('System', $row['granted_by']);
        self::assertArrayNotHasKey('token', $row);
        $this->assertDatabaseHas('role_user', ['role_id' => $this->adminRole->id, 'token' => 'abc']);
    }

    #[Test]
    public function it_logs_a_morph_to_many_relation(): void
    {
        $post = Post::query()->create(['title' => 'Hello']);
        $tag  = Tag::query()->create(['name' => 'php']);

        /** @var PivotActivityFluent<Tag, Post> $fluent */
        // @phpstan-ignore method.notFound
        $fluent = $post->tags()->activity();
        $fluent->attach($tag, ['label' => 'main']);

        $activity = $this->lastActivity();

        self::assertSame('attached', $activity->event);
        self::assertSame('taggables', $this->properties($activity)['table']);
        self::assertSame(Tag::class, $this->properties($activity)['related_type']);

        $row = $this->rowOf($this->listProperty($activity, 'attributes'), $tag->id);

        self::assertSame('main', $row['label']);
        self::assertSame(Post::class, $row['taggable_type']);
    }

    #[Test]
    public function it_logs_activity_with_anonymous_causer(): void
    {
        $this->pivotActivity()
            ->byAnonymous()
            ->attach([$this->adminRole->id]);

        $activity = $this->lastActivity();
        self::assertNull($activity->causer_id);
        self::assertNull($activity->causer_type);
        self::assertEquals('attached', $activity->event);
    }

    #[Test]
    public function it_logs_only_the_rows_that_changed(): void
    {
        $this->user->roles()->attach([$this->adminRole->id, $this->editorRole->id]);

        $this->pivotActivity()->sync([$this->editorRole->id, $this->viewerRole->id]);

        $activity = $this->lastActivity();

        self::assertSame([$this->adminRole->id], array_keys($this->listProperty($activity, 'old')));
        self::assertSame([$this->viewerRole->id], array_keys($this->listProperty($activity, 'attributes')));
    }

    #[Test]
    public function it_resolves_auth_user_as_causer_when_by_called_without_argument(): void
    {
        $admin = User::query()->create(['name' => 'Auth User']);

        // Set the authenticated user directly on the guard so that Auth::user()
        // executes real code (lines traced by PCOV) instead of a Mockery intercept.
        Auth::guard()->setUser($admin);

        $this->pivotActivity()
            ->by()
            ->attach([$this->adminRole->id]);

        $activity = $this->lastActivity();
        self::assertEquals($admin->id, $activity->causer_id);
    }

    #[Test]
    public function it_rolls_the_operation_back_when_the_log_cannot_be_written(): void
    {
        Activity::creating(function (): never {
            throw new RuntimeException('log failed');
        });

        try {
            $this->pivotActivity()->attach($this->adminRole);
            self::fail('The exception of the log must go up.');
        }
        catch (RuntimeException) {
            $this->assertDatabaseMissing('role_user', ['role_id' => $this->adminRole->id]);
        }
        finally {
            Activity::flushEventListeners();
        }
    }

    #[Test]
    public function it_runs_but_writes_nothing_when_spatie_is_disabled(): void
    {
        config()->set('activitylog.enabled', false);
        resolve(ActivityLogStatus::class)->disable();

        $this->pivotActivity()->attach($this->adminRole);

        $this->assertDatabaseHas('role_user', ['role_id' => $this->adminRole->id]);
        self::assertSame(0, Activity::query()->toBase()->count());
    }

    #[Test]
    public function it_skips_detach_when_no_ids_match_existing(): void
    {
        // No roles attached — nothing to detach
        $count = $this->pivotActivity()
            ->detach([$this->adminRole->id]);

        self::assertEquals(0, $count);

        // No activity logged when nothing changed
        self::assertEquals(0, Activity::query()->toBase()->count());
    }

    #[Test]
    public function it_skips_sync_without_detaching_when_all_ids_already_present(): void
    {
        // Attach first
        $this->user->roles()->attach([$this->adminRole->id, $this->editorRole->id]);

        // Try to syncWithoutDetaching with already-present IDs
        $result = $this->pivotActivity()
            ->syncWithoutDetaching([$this->adminRole->id, $this->editorRole->id]);

        self::assertSame(['attached' => [], 'detached' => [], 'updated' => []], $result);

        // No activity logged
        self::assertEquals(0, Activity::query()->toBase()->count());
    }

    #[Test]
    public function it_syncs_with_pivot_attributes(): void
    {
        $this->pivotActivity()->sync([$this->adminRole->id => ['granted_by' => 'System']]);

        $row = $this->rowOf($this->listProperty($this->lastActivity(), 'attributes'), $this->adminRole->id);

        self::assertSame('System', $row['granted_by']);
    }

    #[Test]
    public function it_takes_the_id_of_the_causer_as_spatie_does(): void
    {
        config()->set('auth.providers.users.model', User::class);
        config()->set('auth.providers.users.driver', 'eloquent');

        $admin = User::query()->create(['name' => 'Admin']);

        $this->pivotActivity()->by($admin->id)->attach($this->adminRole);

        self::assertSame($admin->id, $this->lastActivity()->causer_id);
    }

    #[Test]
    public function it_uses_the_default_log_of_spatie_without_a_log_name(): void
    {
        config()->set('laravel-activity.log_name');

        $this->pivotActivity()->attach($this->adminRole);

        self::assertSame(config('activitylog.default_log_name'), $this->lastActivity()->log_name);
    }

    #[Test]
    public function it_uses_the_morph_class_of_the_models(): void
    {
        Relation::morphMap(['member' => User::class]);

        try {
            $this->pivotActivity()->attach($this->adminRole);

            self::assertCount(1, $this->user->activities()->on()->get());
        }
        finally {
            Relation::morphMap([], false);
        }
    }

    #[Test]
    public function it_works_with_collections(): void
    {
        $roles = collect([$this->adminRole, $this->editorRole]);

        $this->pivotActivity()
            ->attach($roles);

        self::assertEquals(2, $this->user->roles()->get()->count());
    }

    #[Test]
    public function it_works_with_model_instances_instead_of_ids(): void
    {
        $this->pivotActivity()
            ->attach($this->adminRole);

        self::assertEquals(1, $this->user->roles()->get()->count());

        $activity = $this->lastActivity();
        self::assertArrayHasKey($this->adminRole->id, $this->listProperty($activity, 'attributes'));
    }

    #[Test]
    public function it_writes_in_another_log_for_a_call(): void
    {
        $this->pivotActivity()->inLog('audit')->attach($this->adminRole);

        self::assertSame('audit', $this->lastActivity()->log_name);
        self::assertCount(0, $this->adminRole->activities()->related()->get());
    }

    #[Test]
    public function it_writes_in_the_configured_log(): void
    {
        $this->pivotActivity()->attach($this->adminRole);
        self::assertSame('pivot', $this->lastActivity()->log_name);
    }

    private function lastActivity(): Activity
    {
        return Activity::query()->firstOrFail();
    }

    /**
     * @return array<int|string, mixed>
     */
    private function listProperty(Activity $activity, string $key): array
    {
        $value = $activity->attribute_changes?->all()[$key] ?? [];

        return is_array($value) ? $value : [];
    }

    /**
     * The activity() macro is registered at runtime, so static analysis cannot see it.
     *
     * @return PivotActivityFluent<Role, User>
     */
    private function pivotActivity(): PivotActivityFluent
    {
        /** @var PivotActivityFluent<Role, User> $fluent */
        // @phpstan-ignore method.notFound
        $fluent = $this->user->roles()->activity();

        return $fluent;
    }

    /**
     * @return array<string, mixed>
     */
    private function properties(Activity $activity): array
    {
        /** @var array<string, mixed> $properties */
        $properties = $activity->properties?->all() ?? [];

        return $properties;
    }

    /**
     * @return array<int, int|string>
     */
    private function roleIds(): array
    {
        return $this->user->roles()->get()->modelKeys();
    }

    /**
     * @param array<int|string, mixed> $rows
     *
     * @return array<string, mixed>
     */
    private function rowOf(array $rows, int|string $id): array
    {
        $row = $rows[$id] ?? [];

        self::assertIsArray($row);

        /** @var array<string, mixed> $row */
        return $row;
    }
}

/**
 * Test User model.
 *
 * @property int    $id
 * @property string $name
 */
class User extends Model implements Authenticatable
{
    use AuthenticatableTrait;
    use TraceActivities;

    protected $guarded = [];

    /**
     * @return BelongsToMany<Role, $this, HiddenPivot, 'pivot'>
     */
    public function hiddenRoles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user')->using(HiddenPivot::class)->withPivot('granted_by');
    }

    /**
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }
}

/**
 * Test Role model.
 *
 * @property int    $id
 * @property string $name
 */
class Role extends Model
{
    use TraceActivities;

    protected $guarded = [];

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }
}

/**
 * Pivot model that hides a column.
 */
class HiddenPivot extends Pivot
{
    protected $hidden = ['granted_by'];
}

/**
 * Test Post model, with a morph to many relation.
 *
 * @property int $id
 */
class Post extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    /**
     * @return MorphToMany<Tag, $this>
     */
    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable')->withPivot('label');
    }
}

/**
 * Test Tag model.
 *
 * @property int $id
 */
class Tag extends Model
{
    public $timestamps = false;

    protected $guarded = [];
}
