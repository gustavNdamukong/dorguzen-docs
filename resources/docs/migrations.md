# Migrations

Migrations let you define and version your database schema in PHP. All migration files live in `database/migrations/`.

---

## Running Migrations

```bash
php dgz migrate                 # run all pending migrations
php dgz migrate:rollback        # roll back the last batch
php dgz migrate:fresh           # drop all tables and re-run from scratch
php dgz migrate:status          # show migration status
```

---

## Creating a Migration

```bash
php dgz make:migration create_products_table
```

Generates a timestamped file in `database/migrations/`:

```php
<?php

use Dorguzen\Core\Database\Migrations\Migration;
use Dorguzen\Core\Database\Migrations\Blueprint;

return new class extends Migration {

    public function up(): void
    {
        $sql = $this->schema->create('products', function (Blueprint $table) {
            $table->id();
            $table->string('product_name');
            $table->decimal('product_price', 10, 2)->nullable();
            $table->timestamps();
        });

        $this->addStatement($sql);
    }

    public function down(): void
    {
        $sql = $this->schema->dropIfExists('products');
        $this->addStatement($sql);
    }
};
```

---

## The Blueprint API

### Column Types

| Method | MySQL type | Notes |
|---|---|---|
| `$table->id('col')` | `INT UNSIGNED AUTO_INCREMENT PRIMARY KEY` | Defaults to `id` |
| `$table->string('col', length)` | `VARCHAR(length)` | Default 255 |
| `$table->text('col')` | `TEXT` | |
| `$table->longText('col')` | `LONGTEXT` | |
| `$table->integer('col', length)` | `INT(length)` | |
| `$table->unsignedInteger('col')` | `INT UNSIGNED` | |
| `$table->tinyInteger('col')` | `TINYINT` | |
| `$table->binary('col')` | `BLOB` | For AES-encrypted fields |
| `$table->boolean('col')` | `TINYINT(1)` | |
| `$table->decimal('col', p, s)` | `DECIMAL(p,s)` | |
| `$table->date('col')` | `DATE` | |
| `$table->dateTime('col')` | `DATETIME` | |
| `$table->timestamp('col')` | `TIMESTAMP` | |
| `$table->enum('col', [values])` | `ENUM(...)` | |
| `$table->json('col')` | `JSON` | |
| `$table->primaryKey('col')` | `PRIMARY KEY (col)` | For non-auto-increment PKs — define the column first |
| `$table->timestamps()` | Adds `created_at` + `updated_at` | Both as TIMESTAMP |

### Column Modifiers

| Modifier | Description |
|---|---|
| `->nullable()` | Allows NULL |
| `->default($value)` | Sets a default |
| `->unique()` | Adds UNIQUE constraint |
| `->notNullable()` | Adds NOT NULL |
| `->useCurrent()` | `DEFAULT CURRENT_TIMESTAMP` |
| `->useCurrentOnUpdate()` | `ON UPDATE CURRENT_TIMESTAMP` |
| `->unsigned()` | Makes numeric column unsigned |

### Indexes

```php
$table->index('column_name');
```

---

## Core Migrations (Shipped with Dorguzen)

| Migration file | Table | Purpose |
|---|---|---|
| `create_dgz_jobs_table` | `dgz_jobs` | Queue job storage |
| `create_dgz_failed_jobs_table` | `dgz_failed_jobs` | Failed job records |
| `create_dgz_refresh_tokens_table` | `refresh_tokens` | JWT refresh tokens |
| `create_users_table` | `users` | User accounts and roles |
| `create_logs_table` | `logs` | Application log entries |
| `create_base_settings_table` | `baseSettings` | Key-value site configuration |
| `create_contact_form_messages_table` | `contactformmessage` | Contact form submissions |
| `create_password_reset_table` | `password_reset` | Password reset tokens |
| `create_seo_table` | `seo` | Per-page SEO metadata |
| `create_seo_global_table` | `seo_global` | Site-wide SEO defaults |
| `dgz_scheduled_task_locks` | `dgz_scheduled_task_locks` | Scheduler distributed locks |
| `create_news_table` | `news` | News articles |
| `create_subscribers_table` | `subscribers` | Newsletter subscribers |
| `create_newsletters_table` | `newsletters` | Newsletter campaigns |
| `create_portfolio_table` | `portfolio` | Portfolio items |
| `create_gallery_albums_table` | `gallery_albums` | Gallery albums |
| `create_gallery_images_table` | `gallery_images` | Gallery images |
| `create_video_albums_table` | `video_albums` | Video albums |
| `create_videos_table` | `videos` | Video entries |
| `create_blog_categories_table` | `blog_categories` | Blog categories |
| `create_blog_posts_table` | `blog_posts` | Blog posts |
| `create_blog_comments_table` | `blog_comments` | Blog comments |

---

## Naming Convention

```
YYYY_MM_DD_HHMMSS_descriptive_name.php
```

The timestamp guarantees migrations run in the correct order. Use descriptive names:

```
2026_06_15_120000_create_orders_table.php
2026_06_15_120001_add_status_column_to_orders.php
```

---

> **Note on `primaryKey()`**: `$table->primaryKey('col')` only adds the `PRIMARY KEY` constraint. The column must be defined separately first with `$table->string('col')` or another column method before calling `primaryKey()`.
