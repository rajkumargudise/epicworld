<?php

use App\Http\Controllers\Admin\ArticleController;
use App\Http\Controllers\Account\AccountController;
use App\Http\Controllers\Account\ContributorPostController;
use App\Http\Controllers\Admin\CommentModerationController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\FeedController;
use App\Http\Controllers\Admin\LaunchController;
use App\Http\Controllers\Admin\MessageController;
use App\Http\Controllers\Admin\ReviewQueueController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\StoryController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Public\ArticleController as PublicArticleController;
use App\Http\Controllers\Public\AdsTxtController;
use App\Http\Controllers\Public\CategoryController;
use App\Http\Controllers\Public\CommentController;
use App\Http\Controllers\Public\HomeController;
use App\Http\Controllers\Public\LatestController;
use App\Http\Controllers\Public\LiveNewsController;
use App\Http\Controllers\Public\NewsSitemapController;
use App\Http\Controllers\Public\PageController;
use App\Http\Controllers\Public\PrivacyController;
use App\Http\Controllers\Public\RobotsController;
use App\Http\Controllers\Public\SearchController;
use App\Http\Controllers\Public\SitemapController;
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
Route::get('/robots.txt', [RobotsController::class, 'index'])->name('robots');
Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');
Route::get('/sitemap-news.xml', NewsSitemapController::class)->name('sitemap.news');
Route::get('/ads.txt', AdsTxtController::class)->name('ads.txt');

Route::get('/about', [PageController::class, 'show'])->defaults('page', 'about')->name('about');
Route::get('/editorial-policy', [PageController::class, 'show'])->defaults('page', 'editorial-policy')->name('editorial.policy');
Route::get('/terms', [PageController::class, 'show'])->defaults('page', 'terms')->name('terms');
Route::get('/write-for-us', [PageController::class, 'show'])->defaults('page', 'write-for-us')->name('write');
Route::get('/contact', [PageController::class, 'contact'])->name('contact');
Route::post('/contact', [PageController::class, 'sendContact'])->middleware('throttle:contact')->name('contact.send');

Route::post('/article/{publicArticle:slug}/comments', [CommentController::class, 'store'])->middleware('throttle:comment')->name('comments.store');

Route::get('/live', [LiveNewsController::class, 'index'])->name('live');
Route::get('/live/{scope}', [LiveNewsController::class, 'index'])->whereIn('scope', ['world', 'news', 'local', 'videos'])->name('live.scope');
Route::get('/live/{scope}/feed', [LiveNewsController::class, 'feed'])->whereIn('scope', ['world', 'news', 'local'])->name('live.feed');
Route::get('/wire/{item}/{slug?}', [LiveNewsController::class, 'show'])->whereNumber('item')->name('wire.show');
Route::get('/latest', [LatestController::class, 'index'])->name('latest');
Route::get('/search', [SearchController::class, 'index'])->name('search');
Route::get('/category/{category:slug}', [CategoryController::class, 'show'])->name('category.show');
Route::get('/article/{publicArticle:slug}', [PublicArticleController::class, 'show'])->name('article.show');
Route::get('/tag/{tag:slug}', [TagController::class, 'show'])->name('tag.show');
Route::get('/privacy', [PrivacyController::class, 'index'])->name('privacy');

// Server-side session auth for the admin/editorial CMS, built on
// Laravel's own 'web' guard - see AuthenticatedSessionController.
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:login');
    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->middleware('throttle:register');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    // Signed-in members (contributors, editors, admins): their own posts and password.
    Route::prefix('account')->name('account.')->middleware('throttle:60,1')->group(function () {
        Route::get('/', [ContributorPostController::class, 'dashboard'])->name('dashboard');
        Route::get('/posts/create', [ContributorPostController::class, 'create'])->name('posts.create');
        Route::post('/posts', [ContributorPostController::class, 'store'])->middleware('throttle:submit-post')->name('posts.store');
        Route::get('/posts/{article}/edit', [ContributorPostController::class, 'edit'])->name('posts.edit');
        Route::put('/posts/{article}', [ContributorPostController::class, 'update'])->middleware('throttle:submit-post')->name('posts.update');
        Route::delete('/posts/{article}', [ContributorPostController::class, 'destroy'])->name('posts.destroy');
        Route::get('/password', [AccountController::class, 'editPassword'])->name('password.edit');
        Route::put('/password', [AccountController::class, 'updatePassword'])->name('password.update');
    });

    // The CMS: editors and admins only (Gate "access-admin"). Individual
    // actions are further restricted by policies (publish/settings = admin).
    Route::prefix('admin')->name('admin.')->middleware('can:access-admin')->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        Route::get('/stories', [StoryController::class, 'index'])->name('stories.index');
        Route::get('/stories/{story}', [StoryController::class, 'show'])->name('stories.show');

        Route::get('/review', [ReviewQueueController::class, 'index'])->name('review.index');
        Route::post('/review/publish', [ReviewQueueController::class, 'publish'])->name('review.publish');
        Route::post('/review/{article}/reject', [ReviewQueueController::class, 'reject'])->name('review.reject');

        Route::get('/comments', [CommentModerationController::class, 'index'])->name('comments.index');
        Route::put('/comments', [CommentModerationController::class, 'update'])->name('comments.update');

        Route::get('/messages', [MessageController::class, 'index'])->name('messages.index');
        Route::patch('/messages/{message}/read', [MessageController::class, 'toggleRead'])->name('messages.read');
        Route::delete('/messages/{message}', [MessageController::class, 'destroy'])->name('messages.destroy');

        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');

        Route::get('/launch', [LaunchController::class, 'index'])->name('launch');

        Route::get('/feeds', [FeedController::class, 'index'])->name('feeds.index');

        Route::get('/settings', [SettingsController::class, 'edit'])->name('settings.edit');
        Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');
        Route::get('/account/password', [SettingsController::class, 'editPassword'])->name('password.edit');
        Route::put('/account/password', [SettingsController::class, 'updatePassword'])->name('password.update');

        Route::get('/articles/{article}/edit', [ArticleController::class, 'edit'])->name('articles.edit');
        Route::put('/articles/{article}', [ArticleController::class, 'update'])->name('articles.update');
        Route::post('/articles/{article}/approve', [ArticleController::class, 'approve'])->name('articles.approve');
        Route::post('/articles/{article}/publish', [ArticleController::class, 'publish'])->name('articles.publish');
        Route::post('/articles/{article}/confirm-sensitive-review', [ArticleController::class, 'confirmSensitiveReview'])->name('articles.confirmSensitiveReview');
    });
});

// Old WordPress URLs -> new article URLs (301), else a normal 404.
Route::fallback(\App\Http\Controllers\Public\LegacyUrlController::class);
