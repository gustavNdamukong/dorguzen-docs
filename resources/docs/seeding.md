# Database Seeding

## What Is Database Seeding?

Database seeding is the process of automatically filling your database with data.

Instead of manually inserting rows into your tables, Dorguzen allows you to:

- Generate realistic test data
- Populate demo environments
- Prepare development databases
- Recreate consistent test datasets

Think of it like planting seeds 🌱 — your database grows with useful content automatically.

---

## The Seeding Architecture (Simple Overview)

Dorguzen's seeding system has 4 layers:

```
CLI Command
    ↓
SeederRunner
    ↓
Seeder Classes
    ↓
Factories + Pools
```

Let's break this down in simple terms.

---

## CLI Commands

Everything starts from the command line.

### Basic command

```bash
php dgz db:seed
```

This runs the main seeder class called:

```
DatabaseSeeder
```

### Run a Specific Seeder

```bash
php dgz db:seed --class=UserSeeder
```

This runs only `UserSeeder`.

### Run All Seeders Explicitly

```bash
php dgz db:seed --all
```

This forces execution of `DatabaseSeeder`.

### Preview Without Running (Pretend Mode)

```bash
php dgz db:seed --pretend
```

This shows what would happen — but does not insert anything.

When the run finishes, Dorguzen confirms with:

```
Because of your --pretend flag, no queries were ran.
```

This is safe for testing.

### Force Seeding in Protected Environments

```bash
php dgz db:seed --force
```

Some environments (like production) are protected.

`--force` overrides that safety check.

### Fresh Migration + Seed

```bash
php dgz migrate:fresh --seed
```

This:

- Drops all tables
- Re-runs all migrations
- Seeds the database

Perfect for starting over.

---

## Seeder Classes

A Seeder is a class responsible for inserting data into a specific table.

Example:

```php
class UserSeeder extends Seeder
{
    protected string $table = 'users';

    public function run(): void
    {
        UserFactory::new()->count(50)->create();
    }
}
```

Simple meaning:

“Create 50 users.”

### The Main Seeder: DatabaseSeeder

This is your master orchestrator.

```php
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(UserSeeder::class);
        $this->call(PostSeeder::class);
    }
}
```

This ensures seeders run in the correct order.

---

## Generating a Seeder from the CLI

You don't have to hand-write seeder files. Generate one with:

```bash
php dgz make:seeder ProductSeeder
# or hint the table it targets:
php dgz make:seeder ProductSeeder --table=products
```

This creates `database/seeders/ProductSeeder.php`, pre-wired with the correct namespace, a `$table` property, a `run()` method, and a `getTable()` method:

```php
namespace Dorguzen\Database\Seeders;

use Dorguzen\Core\Database\Seeders\Seeder;

class ProductSeeder extends Seeder
{
    protected string $table = 'products';

    public function run(): void
    {
        $this->factory(ProductFactory::class)
            ->count(50)
            ->create($this->table);
    }

    public function getTable(): string
    {
        return $this->table;
    }
}
```

If you omit `--table`, Dorguzen guesses the table name from the seeder name.

Inside a seeder, `$this->factory(FactoryClass::class)` returns a factory instance, and `create()` takes the target table name. Every seeder also defines `getTable()`.

---

## Seeding with Raw SQL

Factories are perfect for bulk fake data, but for fixed initial data (settings, a default admin, lookup rows) you can write SQL directly. The `Seeder` base class exposes the database adapter as `$this->db`:

```php
public function run(): void
{
    $sql = "INSERT IGNORE INTO `categories` (`category_name`, `category_slug`)
            VALUES (?, ?)";

    $this->db->execute($sql, ['News', 'news']);
    $this->db->execute($sql, ['Events', 'events']);
}
```

Use `INSERT IGNORE` whenever a unique constraint exists so the seeder is **idempotent** — safe to run again without creating duplicates. This is exactly how the seeders Dorguzen ships with work.

---

## Factories — Automatic Record Generation

Factories generate rows for your tables.

Example:

```php
class PostFactory extends Factory
{
    protected function definition(): array
    {
        return [
            'title' => Pool::get('text.sentence', 6),
            'body'  => Pool::get('text.paragraph', 4),
            'created_at' => Pool::get('date.now'),
            'updated_at' => Pool::get('date.now'),
        ];
    }
}
```

This defines what a “Post” looks like.

When you run:

```php
PostFactory::new()->count(10)->create();
```

Dorguzen will generate 10 posts automatically.

---

## Data Pools — Smart Fake Data

Pools generate realistic fake data.

Instead of hardcoding text like:

```php
'title' => 'Sample Post'
```

You can do:

```php
'title' => Pool::get('text.sentence', 6)
```

And Dorguzen will generate a sentence automatically.

### Available Pools

Here are some examples of built-in pools:

**Text**

```php
Pool::get('text.sentence', 6)
Pool::get('text.paragraph', 3)
Pool::get('text.text', 200)
```

**Names & Emails**

```php
Pool::get('name.full')
Pool::get('email', $name)
```

**Numbers**

```php
Pool::get('number.int', 1, 100)
Pool::get('number.float', 0, 100, 2)
Pool::get('number.numeric', 6)
```

**Dates**

```php
Pool::get('date.now')
```

**Status & Flags**

```php
Pool::get('status')
Pool::get('boolean')
```

This keeps your factories clean and readable.

---

## Factory Lifecycle Hooks

Factories support lifecycle hooks.

### beforeCreate()

Modify attributes before inserting:

```php
protected function beforeCreate(array &$attributes): void
{
    $attributes['slug'] = strtolower(str_replace(' ', '-', $attributes['title']));
}
```

### afterCreate()

Run logic after insertion:

```php
protected function afterCreate(array $attributes): void
{
    // attach relationships
}
```

---

## Unique Values

Factories support unique generation.

Example:

```php
$email = $this->unique('email', function () use ($name) {
    return Pool::get('email', $name);
});
```

This prevents duplicate values in the database.

---

## Environment Protection

Dorguzen protects important environments.

If seeding is attempted in a protected environment, it will throw an error unless:

```bash
--force
```

This prevents accidental data corruption.

---

## How Everything Works Together

Here is how everything works together. When you run:

```bash
php dgz db:seed
```

Here's what happens internally:

- CLI reads your options
- SeederRunner initializes
- DatabaseSeeder runs
- Individual seeders (your custom seeders in `database/seeders/`) execute
- Factories (called from your custom seeders) generate records
- Pools (that you use in your factories) generate fake data
- Database is populated

This all happens automatically when you run the seed command:

```bash
php dgz db:seed
```

The Dorguzen seeding system is designed to be:

- Deterministic
- Environment-aware
- CLI-driven
- Extensible
- Cleanly layered
- Dependency-free (no external faker libraries)

It is designed to scale from small hobby projects to Professional applications and even Large systems.

---

## When Should You Use Seeding?

Use seeding when:

- Starting a new development environment
- Resetting a test database
- Preparing demo data
- Running automated tests
- Onboarding new developers

If you remember only three things, let it be these:

- Factories define how a table row looks.
- Seeders decide how many rows to create.
- CLI runs everything.

---

## The SuperAdminSeeder — Your First Login

Dorguzen ships with a `SuperAdminSeeder` that creates the initial super admin account your application needs from day one. This seeder is registered in `DatabaseSeeder` and is safe to run at any time — it uses `INSERT IGNORE`, so running it more than once will never create a duplicate record.

### Default super admin credentials

```
First name : Dorguzen
Last name  : Admin
Email      : admin@dorguzen.com
Password   : Admin123
```

Use these credentials to log in for the first time. Once you are logged in, go to the admin dashboard and update the super admin's details (name, email, and password) to values specific to your application before going live.

Changing the password can be done from Admin > Manage Users > Edit User (or use the "Admin Change Password" option in the admin dashboard).

### Running migrations and the seeder

After setting up your database and running your migrations, run the super admin seeder immediately so the account is available before you open the app in a browser:

```bash
# 1. Run all migrations first
php dgz migrate

# 2. Seed the database (creates the super admin account)
php dgz db:seed
```

Or, if you want to reset everything from scratch:

```bash
php dgz migrate:fresh --seed
```

The seeder is idempotent — it is safe to run it multiple times.

---

## The BaseSettingsSeeder — Default Site Configuration

Alongside `SuperAdminSeeder`, Dorguzen also registers a `BaseSettingsSeeder` in `DatabaseSeeder`. It seeds the `baseSettings` table with the default site configuration your application reads at runtime — for example the brand-slider toggle and its image source, and the app colour theme. Like the super-admin seeder it uses `INSERT IGNORE`, so it is safe to re-run:

```php
class BaseSettingsSeeder extends Seeder
{
    protected string $table = 'baseSettings';

    public function run(): void
    {
        $sql = "INSERT IGNORE INTO `baseSettings`
                    (`settings_name`, `settings_value`)
                VALUES ('show_brand_slider', 'true'),
                       ('brand_slider_source', 'assets/images/gallery'),
                       ('app_color_theme', '#0d6efd')";

        $this->db->execute($sql);
    }

    public function getTable(): string
    {
        return $this->table;
    }
}
```
