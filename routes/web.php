<?php

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\ProjectController;
use App\Http\Controllers\Admin\TaskController;
use App\Http\Controllers\GoogleAuthController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\PortalController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'home')->name('home');

// ---- client onboarding + portal (token link, no login) ----
Route::get('/new', [OnboardingController::class, 'create'])->name('onboarding.create');
Route::post('/projects', [OnboardingController::class, 'store'])->name('onboarding.store');

Route::prefix('/p/{project:token}')->name('portal.')->group(function () {
    Route::get('/', [PortalController::class, 'show'])->name('show');
    Route::post('/tasks/{task}/respond', [PortalController::class, 'respond'])->name('respond');
    Route::post('/clarity', [PortalController::class, 'clarity'])->name('clarity');
    Route::get('/google', [PortalController::class, 'googleOptions'])->name('google');
    Route::post('/google', [PortalController::class, 'googleSelect'])->name('google.select');
    Route::post('/google/disconnect', [PortalController::class, 'googleDisconnect'])->name('google.disconnect');
    Route::get('/google/start', [GoogleAuthController::class, 'start'])->name('google.start');
});
Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->name('google.callback');

// ---- admin ----
Route::get('/admin/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/admin/login', [AuthController::class, 'login'])->middleware('throttle:10,1')->name('admin.login');
Route::post('/admin/logout', [AuthController::class, 'logout'])->name('admin.logout');

Route::prefix('/admin')->middleware('auth')->name('admin.')->group(function () {
    Route::get('/', [ProjectController::class, 'index'])->name('projects.index');
    Route::get('/projects/{project}', [ProjectController::class, 'show'])->name('projects.show');
    Route::put('/projects/{project}', [ProjectController::class, 'update'])->name('projects.update');
    Route::delete('/projects/{project}', [ProjectController::class, 'destroy'])->name('projects.destroy');
    Route::post('/projects/{project}/sync', [ProjectController::class, 'sync'])->name('projects.sync');
    Route::post('/projects/{project}/analyze', [ProjectController::class, 'analyze'])->name('projects.analyze');
    Route::post('/projects/{project}/publish', [ProjectController::class, 'publish'])->name('projects.publish');
    Route::post('/projects/{project}/tasks', [TaskController::class, 'store'])->name('tasks.store');
    Route::put('/tasks/{task}', [TaskController::class, 'update'])->name('tasks.update');
    Route::post('/tasks/{task}/review', [TaskController::class, 'review'])->name('tasks.review');
    Route::delete('/tasks/{task}', [TaskController::class, 'destroy'])->name('tasks.destroy');
});
