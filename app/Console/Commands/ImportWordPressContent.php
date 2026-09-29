<?php

namespace App\Console\Commands;

use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Models\Category;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PDO;

/**
 * One-off, idempotent import of the site's existing WordPress content
 * (published posts only) into the Laravel articles/categories tables,
 * ahead of the M19-C cutover. Read-only against WordPress: this command
 * never writes to the WordPress database, only SELECTs from it.
 *
 * Connects to the WordPress database by parsing its own wp-config.php
 * (given via --wp-config) rather than accepting credentials on the
 * command line or storing them in this app's .env, so the WordPress
 * DB password never has to be typed, echoed, or persisted anywhere in
 * this codebase.
 *
 * Idempotent: an article already imported from a given WordPress post
 * (tracked via editorial_metadata->wp_post_id) is skipped on re-run,
 * so this command is safe to run more than once.
 */
class ImportWordPressContent extends Command
{
    protected $signature = 'wordpress:import
        {--wp-config= : Absolute path to the WordPress wp-config.php file}
        {--dry-run : Report what would be imported without writing anything}
        {--author= : Email of the Laravel user to attribute imported articles to}';

    protected $description = 'Import published WordPress posts (read-only from WordPress) into the Laravel articles/categories tables.';

    public function handle(): int
    {
        $wpConfigPath = $this->option('wp-config');

        if (! $wpConfigPath || ! is_file($wpConfigPath)) {
            $this->error('Pass --wp-config=/absolute/path/to/wp-config.php (a readable WordPress config file).');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');

        $creds = $this->parseWpConfig($wpConfigPath);

        if (! $creds) {
            $this->error('Could not parse DB_NAME/DB_USER/DB_PASSWORD/DB_HOST out of that wp-config.php.');

            return self::FAILURE;
        }

        $wpdb = new PDO(
            "mysql:host={$creds['host']};dbname={$creds['name']};charset=utf8mb4",
            $creds['user'],
            $creds['pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        $authorId = null;
        if ($email = $this->option('author')) {
            $authorId = DB::table('users')->where('email', $email)->value('id');
            if (! $authorId) {
                $this->warn("No Laravel user found with email {$email}; articles will be imported without an author.");
            }
        }

        $posts = $wpdb->query("
            SELECT p.ID, p.post_title, p.post_content, p.post_excerpt, p.post_date, p.post_name
            FROM wp_posts p
            WHERE p.post_type = 'post' AND p.post_status = 'publish'
            ORDER BY p.post_date ASC
        ")->fetchAll(PDO::FETCH_ASSOC);

        $this->info(sprintf('%d published WordPress post(s) found.', count($posts)));

        $catStmt = $wpdb->prepare("
            SELECT t.name
            FROM wp_term_relationships tr
            JOIN wp_term_taxonomy tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
            JOIN wp_terms t ON tt.term_id = t.term_id
            WHERE tt.taxonomy = 'category' AND tr.object_id = :post_id
            LIMIT 1
        ");

        $thumbStmt = $wpdb->prepare("
            SELECT g.guid
            FROM wp_postmeta pm
            JOIN wp_posts g ON g.ID = pm.meta_value
            WHERE pm.post_id = :post_id AND pm.meta_key = '_thumbnail_id'
            LIMIT 1
        ");

        $imported = 0;
        $skipped = 0;

        foreach ($posts as $row) {
            $wpId = (int) $row['ID'];

            $exists = Article::whereJsonContains('editorial_metadata->wp_post_id', $wpId)->exists();
            if ($exists) {
                $skipped++;

                continue;
            }

            $catStmt->execute(['post_id' => $wpId]);
            $categoryName = $catStmt->fetchColumn() ?: 'Uncategorized';

            $category = Category::firstOrCreate(
                ['slug' => Str::slug($categoryName)],
                ['name' => $categoryName, 'is_active' => true]
            );

            $thumbStmt->execute(['post_id' => $wpId]);
            $featuredImage = $thumbStmt->fetchColumn() ?: null;

            $plainContent = $this->htmlToPlainText($row['post_content']);
            $excerpt = trim($row['post_excerpt']) !== ''
                ? $this->htmlToPlainText($row['post_excerpt'])
                : Str::limit($plainContent, 200);

            $title = html_entity_decode($row['post_title'], ENT_QUOTES, 'UTF-8');
            $title = trim(preg_replace('/[\s\x{00A0}]+/u', ' ', $title));

            $slug = $row['post_name'] ?: Str::slug($title);
            $slug = $this->uniqueSlug($slug);

            $this->line(sprintf(
                '%s "%s" -> category "%s", slug "%s"',
                $dryRun ? '[dry-run] would import' : 'importing',
                $title,
                $categoryName,
                $slug,
            ));

            if (! $dryRun) {
                Article::create([
                    'category_id' => $category->id,
                    'author_id' => $authorId,
                    'title' => $title,
                    'slug' => $slug,
                    'content' => $plainContent,
                    'excerpt' => $excerpt,
                    'status' => ArticleStatus::Published,
                    'content_type' => 'article',
                    'featured_image' => $featuredImage,
                    'published_at' => $row['post_date'],
                    'allow_indexing' => true,
                    'editorial_metadata' => ['wp_post_id' => $wpId, 'imported_from' => 'wordpress'],
                ]);
            }

            $imported++;
        }

        $this->info(sprintf(
            '%s: %d imported, %d already-imported skipped.',
            $dryRun ? 'Dry run complete' : 'Import complete',
            $imported,
            $skipped,
        ));

        return self::SUCCESS;
    }

    /**
     * @return array{name:string,user:string,pass:string,host:string}|null
     */
    private function parseWpConfig(string $path): ?array
    {
        $contents = file_get_contents($path);

        $get = function (string $key) use ($contents): ?string {
            if (preg_match("/define\(\s*'{$key}'\s*,\s*'([^']*)'/", $contents, $m)) {
                return $m[1];
            }

            return null;
        };

        $name = $get('DB_NAME');
        $user = $get('DB_USER');
        $pass = $get('DB_PASSWORD');
        $host = $get('DB_HOST') ?: 'localhost';

        if (! $name || ! $user || $pass === null) {
            return null;
        }

        return compact('name', 'user', 'pass', 'host');
    }

    /**
     * WordPress post_content is HTML (often with Gutenberg block
     * comments). Article::content is rendered as plain text (see
     * Article::displayContentHtml's docblock - it escapes every
     * character before adding paragraph tags), so this strips block
     * comments and tags down to clean paragraphs rather than importing
     * markup that would otherwise be escaped and shown literally.
     */
    private function htmlToPlainText(string $html): string
    {
        $text = preg_replace('/<!--\s*\/?wp:.*?-->/s', '', $html);
        $text = preg_replace('/<(p|div|br|h[1-6])[^>]*>/i', "\n", $text);
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        $text = preg_replace("/\n{3,}/", "\n\n", $text);

        return trim($text);
    }

    private function uniqueSlug(string $base): string
    {
        $slug = $base;
        $i = 2;

        while (Article::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }
}
