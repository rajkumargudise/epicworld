<?php

use App\Http\Controllers\Admin\ArticleController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\StoryController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Public\ArticleController as PublicArticleController;
use App\Http\Controllers\Public\CategoryController;
use App\Http\Controllers\Public\HomeController;
use App\Http\Controllers\Public\LatestController;
use App\Http\Controllers\Public\SearchController;
use App\Http\Controllers\Public\TagController;
use App\Models\Article;
use App\Models\Category;
use Illuminate\Support\Facades\Route;

/*
 * Route-model binding for the public site is deliberately scoped, not
 * the plain implicit binding {article} would give: a slug that exists
 * but belongs to a draft, a Review article, or an inactive category
 * must 404 like any other unmatched route, not resolve the model and
 * let a controller decide afterwards.
 *
 * Route::bind() registers by parameter name globally, so it would
 * otherwise also intercept the admin CMS's own {article}/{category}
 * parameters (routes/web.php's admin group) and wrongly scope an
 * editor's access to only publicly-visible content. The public
 * article route therefore uses a distinct parameter name
 * ({publicArticle}) reserved for this binding; {category} isn't used
 * anywhere in the admin routes, so no rename is needed there, but the
 * binding is still named deliberately to make that non-collision
 * obvious rather than accidental.
 */
Route::bind('publicArticle', function (string $slug) {
    return Article::publiclyVisible()->where('slug', $slug)->firstOrFail();
});

Route::bind('category', function (string $slug) {
    return Category::active()->where('slug', $slug)->firstOrFail();
});

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/latest', [LatestController::class, 'index'])->name('latest');
Route::get('/search', [SearchController::class, 'index'])->name('search');
Route::get('/category/{category:slug}', [CategoryController::class, 'show'])->name('category.show');
Route::get('/article/{publicArticle:slug}', [PublicArticleController::class, 'show'])->name('article.show');
Route::get('/tag/{tag:slug}', [TagController::class, 'show'])->name('tag.show');

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
