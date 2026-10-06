<?php

namespace App\Providers;

use App\Models\Car;
use App\Models\CarResponse;
use App\Models\CarRound;
use App\Models\Category;
use App\Models\Subcategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Relation::enforceMorphMap(['car' => Car::class, 'car_response' => CarResponse::class, 'car_round' => CarRound::class, 'category' => Category::class, 'subcategory' => Subcategory::class, 'user' => User::class]);

        $this->defineRoleGates();
    }

    /**
     * Area-level gates, one per sidebar section. Per-CAR rules move into CarPolicy in Phase 2.
     */
    private function defineRoleGates(): void
    {
        Gate::define('view-dashboard', fn (User $user): bool => $user->role->canViewDashboard());
        Gate::define('create-cars', fn (User $user): bool => $user->role->canCreateCars());
        Gate::define('view-queue', fn (User $user): bool => $user->role->canViewQueue());
        Gate::define('view-overdue', fn (User $user): bool => $user->role->canViewOverdue());
        Gate::define('administer', fn (User $user): bool => $user->role->canAdminister());
    }
}
