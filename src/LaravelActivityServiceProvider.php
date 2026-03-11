<?php

declare(strict_types=1);

namespace Zairakai\LaravelActivity;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\ServiceProvider;
use Zairakai\LaravelActivity\Support\PivotActivityFluent;

/**
 * Laravel Activity Helpers Package Service Provider.
 *
 * Provides enhanced activity logging functionality including:
 * - Comprehensive activity tracking with bidirectional relationships
 * - Pivot table operations with detailed before/after logging
 * - Optimized logging configuration for cleaner audit trails
 */
class LaravelActivityServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerPivotActivityMacro();
    }

    /**
     * Register services into the container.
     */
    public function register(): void
    {
        // No services to register for this traits-only package
    }

    /**
     * Register the activity() macro on BelongsToMany relationships.
     *
     * Enables fluent API for pivot activity logging:
     * $user->roles()->activity()->attach([1, 2, 3]);
     */
    protected function registerPivotActivityMacro(): void
    {
        BelongsToMany::macro('activity', fn (): PivotActivityFluent => new PivotActivityFluent($this));
    }
}
