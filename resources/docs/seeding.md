# Database Seeding

Seeders insert initial or test data into the database. All seeder files live in `database/seeders/`.

---

## Running Seeders

```bash
php dgz db:seed                              # run DatabaseSeeder (seeds everything)
php dgz db:seed --class=SuperAdminSeeder     # run one specific seeder
php dgz db:seed --pretend                    # print SQL without executing
php dgz db:seed --force                      # force seeding in protected environments
```

---

## DatabaseSeeder

The entry point. Add all seeders you want to run by default here:

```php
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(SuperAdminSeeder::class);
        $this->call(BaseSettingsSeeder::class);
    }
}
```

---

## Included Seeders

### SuperAdminSeeder

Seeds the default super-admin account using `INSERT IGNORE` — safe to re-run.

```
Email:    admin@dorguzen.com
Password: Admin123
```

Change these credentials immediately after your first login. The password is stored AES-encrypted using your `DB_KEY` value.

### BaseSettingsSeeder

Seeds the `baseSettings` table with default site configuration values. Also uses `INSERT IGNORE`.

---

## Creating a Seeder

```bash
php dgz make:seeder ProductSeeder
# or with a table hint:
php dgz make:seeder ProductSeeder --table=products
```

Generates `database/seeders/ProductSeeder.php`:

```php
namespace Dorguzen\Database\Seeders;

use Dorguzen\Core\Database\Seeders\Seeder;

class ProductSeeder extends Seeder
{
    protected string $table = 'products';

    public function run(): void
    {
        // seed logic here
    }
}
```

---

## Writing Seeder Logic

The `Seeder` base class provides:

- `$this->db` — the database adapter; use `$this->db->execute($sql, $bindings)` for parameterized queries
- `$this->call(SeederClass::class)` — run another seeder from within a seeder
- `$this->pretend` — `true` when `--pretend` flag is set

### Seeding with raw SQL

For known initial data, write parameterized SQL directly:

```php
public function run(): void
{
    $sql = "INSERT IGNORE INTO `categories` (`category_name`, `category_slug`) VALUES (?, ?)";
    $this->db->execute($sql, ['News', 'news']);
    $this->db->execute($sql, ['Events', 'events']);
}
```

Use `INSERT IGNORE` whenever a unique constraint exists so the seeder is idempotent (safe to re-run).

### Seeding with a Factory

```php
public function run(): void
{
    $this->factory(ProductFactory::class)
        ->count(50)
        ->create($this->table);
}
```

---

## Adding a Seeder to the Default Run

Add a `$this->call()` line to `DatabaseSeeder::run()`:

```php
$this->call(ProductSeeder::class);
```

Or run it in isolation:

```bash
php dgz db:seed --class=ProductSeeder
```

---

## The --pretend Flag

Prints the SQL that would be executed without running it:

```bash
php dgz db:seed --pretend
```

Output ends with: `Because of your --pretend flag, no queries were ran.`
