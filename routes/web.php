<?php

use App\Http\Controllers\Admin\UserDirectoryDebugController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\Auth\AppLoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\CarExportController;
use App\Http\Controllers\CarPrintController;
use App\Http\Middleware\EnsureUserIsActive;
use App\Livewire\Admin;
use App\Livewire\Auth\Login;
use App\Livewire\Cars;
use App\Livewire\Dashboard;
use App\Livewire\Notifications;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect(auth()->user()->role->homeUrl())
        : redirect()->route('login');
})->name('home');

Route::middleware('guest')->group(function () {
    Route::livewire('/login', Login::class)->name('login');
});

Route::get('/app-login/{id}', AppLoginController::class)->name('app.login');

Route::middleware(['auth', EnsureUserIsActive::class])->group(function () {
    Route::post('/logout', LogoutController::class)->name('logout');

    Route::livewire('/dashboard', Dashboard\Index::class)->middleware('can:view-dashboard')->name('dashboard');

    Route::livewire('/cars', Cars\Index::class)->name('cars.index');
    Route::livewire('/cars/create', Cars\Create::class)->middleware('can:create-cars')->name('cars.create');
    Route::get('/cars/export', CarExportController::class)->name('cars.export');
    Route::livewire('/cars/{car:reference}', Cars\Show::class)->name('cars.show');
    Route::livewire('/cars/{car:reference}/edit', Cars\Create::class)->name('cars.edit');
    Route::get('/cars/{car:reference}/print', CarPrintController::class)->name('cars.print');

    Route::get('/attachments/{attachment}', AttachmentController::class)->name('attachments.show');

    Route::livewire('/notifications', Notifications\Index::class)->name('notifications');

    Route::prefix('admin')->name('admin.')->middleware('can:administer')->group(function () {
        Route::livewire('/users', Admin\Users::class)->name('users');
        Route::livewire('/matrix', Admin\Matrix::class)->name('matrix');
        Route::livewire('/audit', Admin\AuditLog::class)->name('audit');

        // TEMPORARY — delete before go-live (checks the real user-list API response).
        Route::get('/debug/user-api', UserDirectoryDebugController::class)->name('debug.user-api');
    });
});
