<?php

use App\Http\Controllers\Admin\ArticleController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\StoryController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Server-side session auth for the admin/editorial CMS, built on
// Laravel's own 'web' guard - see AuthenticatedSessionController.
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store']);
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        Route::get('/stories', [StoryController::class, 'index'])->name('stories.index');
        Route::get('/stories/{story}', [StoryController::class, 'show'])->name('stories.show');

        Route::get('/articles/{article}/edit', [ArticleController::class, 'edit'])->name('articles.edit');
        Route::put('/articles/{article}', [ArticleController::class, 'update'])->name('articles.update');
        Route::post('/articles/{article}/approve', [ArticleController::class, 'approve'])->name('articles.approve');
        Route::post('/articles/{article}/publish', [ArticleController::class, 'publish'])->name('articles.publish');
        Route::post('/articles/{article}/confirm-sensitive-review', [ArticleController::class, 'confirmSensitiveReview'])->name('articles.confirmSensitiveReview');
    });
});
