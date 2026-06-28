# Migrations

The Dorguzen migration system is responsible for:

- Creating tables
- Modifying schema
- Tracking which migrations have already run
- Preventing concurrent migration execution
- Supporting rollbacks
- Resetting databases safely

It is composed of:

**Infrastructure Tables:**

```
dgz_migrations
dgz_migration_locks
```

**Core Classes:**

```
Blueprint
Schema
Migration
MigrationRepository
MigrationLockRepository
```

---

## ⚠️ Infrastructure Requirement

For the migration system to function, the following tables must already exist:

```
dgz_migrations
dgz_migration_locks
```

You do NOT create migration files for these tables.
They are infrastructure tables created automatically by the migration system itself.
They are protected and never dropped by normal reset operations.

---

## 1️⃣ The Infrastructure Tables

There are two: `dgz_migrations` and `dgz_migration_locks`. You do not have to create them. Dorguzen will create them for you. This is just an explanation of how they work.

### a) The migrations table

```
dgz_migrations
```

This table tracks which migrations have already been executed.

**Structure:**

```
id (INT AUTO_INCREMENT PRIMARY KEY)
migration (VARCHAR)
batch (INT)
created_at (TIMESTAMP NULL CURRENT_TIMESTAMP)
```

Here is what happens in this table:

- It prevents migrations from running more than once.
- It groups migrations into batches.
- It enables rollbacks per batch.

### b) The migrations_lock table

```
dgz_migration_locks
```

This table prevents two migration processes from running simultaneously.

**Structure:**

```
id (INT PRIMARY KEY)
locked_at (DATETIME NULL DEFAULT NULL)
```

Only ONE row is ever used:

```
id = 1
```

The way it works is the system will record that a migration is currently running here so it is not run by any other script. It deletes it from here when it's done and marks the migration file as ran in the migrations table.
Note carefully that this id field is not incremented, because every time a record is inserted in here, its value will always be 1.

This is a database-level mutex.

---

## 2️⃣ Migration Execution Flow (Internal Mechanics)

When migrations are executed:

### Step 1 — Ensure Infrastructure Tables Exist

Both repositories call:

```php
ensureTableExists()
```

This guarantees that:

```
dgz_migrations exists
dgz_migration_locks exists
```

Again, these two tables will be created if they do not exist already, which means you could create them yourself, but you do not have to. It's recommended to let the system create them for you, as you may get the fields and their types wrong.

### Step 2 — Acquire Lock

```php
MigrationLockRepository->acquire()
```

It attempts:

```sql
INSERT INTO dgz_migration_locks (id, locked_at)
VALUES (1, CURRENT_TIMESTAMP)
```

If it fails:

- It means another migration process is running
- So the execution is stopped
- and a RuntimeException thrown

This prevents concurrent migrations.

### Step 3 — Determine Which Migrations Have Run

```php
MigrationRepository->getRan()
```

This fetches:

```sql
SELECT migration FROM dgz_migrations
```

Any migration file already recorded here will NOT be re-executed.

### Step 4 — Run New Migrations

For each new migration:

- Instantiate migration class
- The `up()` method of the migration will be called
- The SQL statements of the migration via `addStatement()` is collected
- Each of the SQL statements is executed
- The specific migration is then inserted into the `dgz_migrations` table, thereby logging it as ran

### Step 5 — Release Lock

After execution:

```php
MigrationLockRepository->release()
```

Which runs:

```sql
DELETE FROM dgz_migration_locks WHERE id = 1
```

The migration file that was being run is now unlocked again.

---

## 3️⃣ The Migration Base Class

Every migration class extends:

```
the Migration abstract class
```

The Migration class provides:

```php
$this->schema (Schema instance)
$this->addStatement()
$this->getStatements()
```

You must implement:

```php
public function up(): void
public function down(): void
```

Here is how they work: The `up()` and `down()` methods do NOT execute SQL directly.
Instead, they do this to collect their SQL statements:

```php
$this->addStatement($sql);
```

The migration runner later executes the collected SQL statements.

This allows for:

- Controlled execution
- Logging
- the possibility of rollbacks
- Dry runs (future possibility)

---

## 4️⃣ The Schema Class

Schema (the class) is responsible for:

- converting Blueprint definitions into SQL.
- Creating Tables e.g.

```php
$sql = $this->schema->create('users', function (Blueprint $table) {
    ...
});
```

This Schema class:

- Instantiates Blueprint
- Passes it to your callback
- Calls `$blueprint->toSqlCreate()`
- Returns the final SQL string

It does NOT execute it automatically.

You must use `addStatement(...)` to prepare the SQL for that method (`up()` or `down()`) to be queued:

```php
$this->addStatement($sql);
```

### Dropping tables

The Schema class is also responsible for dropping Tables. This happens in the `down()` method of the migration class. For example:

```php
public function down(): void
{
    // rollback
    $sql = $this->schema->dropIfExists('users');
    $this->addStatement($sql);
}
```

---

## 5️⃣ Blueprint — The Table DSL

Blueprint is your schema builder DSL.

It generates SQL for:

```sql
CREATE TABLE `table` (...)
ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
```

Column Types Available

How to generate database field schemas:

### Primary Key

```php
$table->id();
```

Creates:

```
BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
```

You can customize it like this:

```php
$table->id('user_id');
```

### Custom Primary Key

```php
$table->primaryKey('uuid');
```

Which will create:

```
VARCHAR(255) PRIMARY KEY
```

This will be useful for UUIDs

### Composite key patterns

Foreign ID (No constraints yet)

```php
$table->foreignId('user_id');
```

Creates:

```
INT UNSIGNED
```

(Foreign key constraints may be added later.)

### String

```php
$table->string('name');
$table->string('name', 100);
```

Creates:

```
VARCHAR(255)
VARCHAR(100)
```

### Integer

```php
$table->integer('age');
$table->unsignedInteger('score');
```

### Decimal

```php
$table->decimal('price', 10, 2);
```

Creates:

```
DECIMAL(10,2)
```

### Enum

```php
$table->enum('status', ['pending', 'approved', 'rejected']);
```

Creates:

```
ENUM('pending','approved','rejected') NOT NULL
```

### Text Types

```php
$table->text('bio');
$table->longText('content');
```

```
TEXT (64KB)
LONGTEXT (~5GB)
```

### Dates & Timestamps

```php
$table->date('birth_date');
$table->timestamp('verified_at');
$table->timestamps();
```

`timestamps()` adds:

```
created_at
updated_at
```

### Tiny Integer

```php
$table->tinyInteger('flag');
```

Creates:

```
TINYINT
```

### Binary

```php
$table->binary('payload');
```

Creates:

```
BLOB
```

Useful for AES-encrypted fields and raw binary data.

### DateTime

```php
$table->dateTime('verified_at');
```

`dateTime()` is an alias of `timestamp()` and creates a nullable column:

```
DATETIME NULL
```

### Unique Index

```php
$table->unique('email');
```

Adds:

```sql
UNIQUE (`email`)
```

### Plain Index

```php
$table->index('user_id');
```

Adds (MySQL):

```sql
INDEX (`user_id`)
```

On SQLite it is emitted as a separate `CREATE INDEX` statement, since SQLite does not support inline `INDEX` in `CREATE TABLE`.

### Column Modifiers

Modifiers are chained onto a column definition to refine it:

```php
$table->string('email')->unique();
$table->string('name')->nullable();
$table->string('status')->notNullable();
$table->string('role')->default('user');
$table->integer('score')->unsigned();
$table->timestamp('created_at')->useCurrent();
$table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
```

| Modifier | Emits | Notes |
|---|---|---|
| `->nullable()` | `NULL` | Allows NULL values |
| `->notNullable()` | `NOT NULL` | Explicitly marks the column NOT NULL |
| `->unique()` | `UNIQUE` | Column-level unique constraint |
| `->unsigned()` | appends ` UNSIGNED` to the type | Makes a numeric column unsigned |
| `->default($value)` | `DEFAULT <value>` | String values are auto-quoted |
| `->useCurrent()` | `DEFAULT CURRENT_TIMESTAMP` | |
| `->useCurrentOnUpdate()` | `ON UPDATE CURRENT_TIMESTAMP` | MySQL only; skipped on SQLite |

---

## 6️⃣ Creating a Migration File

To create a migration, run this command:

```bash
php dgz make:migration create_users_table
```

This will generate a migration file stub for you to easily edit:

Tip: if you are creating a model and a migration together, you can do both in one command using `make:model` with the `-m` flag — see the "CREATING A MODEL (CLI)" section above.

```bash
php dgz make:model Products -m                       // auto-names the migration create_products_table
php dgz make:model Products -m create_products_table // same, but with an explicit migration name
```

```
database/migrations/YYYY_MM_DD_HHMMSS_create_users_table.php
```

### Example Migration File

```php
<?php

use Dorguzen\Core\Database\Migrations\Migration;
use Dorguzen\Core\Database\Migrations\Blueprint;

return new class extends Migration {

    public function up(): void
    {
        $sql = $this->schema->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->unique();
            $table->string('password');
            $table->timestamps();
        });

        $this->addStatement($sql);
    }

    public function down(): void
    {
        $sql = $this->schema->dropIfExists('users');
        $this->addStatement($sql);
    }
};
```

---

## 7️⃣ What Happens When You Run Migrations

Let's say you run migrations. Internally, here is the process that runs:

- Lock is acquired (meaning the script sets a value in the `dgz_migration_locks` table to signal to all other migrations scripts that it is already running, so they should wait for it to finish. Every migration script does the same thing, but when trying to set the lock, the inserting will basically fail if a lock record set by another script is already there. This is because only one record is ever set in the `dgz_migration_locks` table, and its id is always the same, 1).
- It checks the `dgz_migrations` table to see if that migration file has not already been ran
- If it is determined that it is a new migration
  - The migration's `up()` called
  - which collects all the SQL of that migration
  - The SQL statements are executed
  - That migration file is then logged as ran in `dgz_migrations` like so:

```sql
INSERT INTO dgz_migrations (migration, batch, created_at)
```

- The lock is released (meaning the entry earlier set in `dgz_migration_locks` is deleted).

---

## 8️⃣ Batches Explained

Each migration run increments batch number. For example:

First run:

```
Batch 1:
- create_users_table
- create_posts_table
```

Secondly, run:

```
Batch 2:
- add_status_to_users
```

Rollback will remove entire last batch.

---

## 9️⃣ Rollbacks

When rolling back, the last batch is fetched for each migration in reverse order:

```php
getLastBatchMigrations()
```

Call `down()`

This method:

- Runs the SQL in that migration's `down()` method which drops the table that migration created in `up()`
- it then removes the entry of that migration from the `dgz_migrations` table

---

## 🔟 Resetting Database

```php
dropAllNonInfrastructureTables()
```

Drops all tables except:

```
dgz_migrations
dgz_migration_locks
```

This allows:

- Clean rebuilds
- Safe resets
- Infrastructure preservation

---

## 1️⃣1️⃣ Why the Lock System Matters

Without locks:

- Two developers could run migrations simultaneously
- Tables could partially create
- Schema corruption possible

With `dgz_migration_locks`:

- Only one migration process runs
- Guaranteed safe schema changes

---

## 1️⃣2️⃣ Key Design Decisions

Dorguzen migrations:

- ✔ Are SQL-first
- ✔ Are explicit
- ✔ Collect statements before execution
- ✔ Prevent concurrent execution
- ✔ Track batches
- ✔ Support full rollback
- ✔ Protect infrastructure tables

---

## 1️⃣3️⃣ Mental Model for Developers

When writing a migration:

You are NOT writing raw SQL.

You are:

- Using Blueprint DSL
- Generating SQL
- Adding statements
- Letting Dorguzen execute safely
- Logging migration

### Final Summary

The Dorguzen Migration System provides:

- Infrastructure tracking (`dgz_migrations`)
- Concurrency safety (`dgz_migration_locks`)
- Blueprint DSL for table creation
- SQL collection system via Migration
- Safe batch-based rollback
- Full database reset options
- Production-safe locking

Developers only need to:

```bash
php dgz make:migration create_table
```

Then:

- Edit file
- Use Blueprint
- Run migrations

And Dorguzen guarantees safe schema evolution.

---

## Running Migrations (CLI Commands)

Dorguzen provides CLI commands to:

- Run all pending migrations
- Run a specific migration file
- Roll back migrations
- Reset the database

These commands work together with:

- MigrationRepository
- MigrationLockRepository
- Migration
- Schema
- Blueprint

---

### 1️⃣ Run All Pending Migrations

```bash
php dgz migrate
```

**What This Does**

Ensures infrastructure tables exist:

```
dgz_migrations
dgz_migration_locks
```

- Acquires migration lock.
- Reads all files inside the directory:

```
database/migrations/
```

- Compares them against:

```
dgz_migrations
```

- Runs only migrations that have NOT been logged.
- Logs each migration into `dgz_migrations`.
- Releases the lock.

**Important**

If a migration already exists in `dgz_migrations`, it will NOT run again.

This prevents accidental duplicate execution.

---

### 2️⃣ Run a Specific Migration File

```bash
php dgz migrate --file=2025_01_01_000000_create_users_test_table.php
```

**What This Does**

- Executes only the specified migration file.
- Does NOT run others.
- Still respects locking.
- Still logs into `dgz_migrations`.
- Will NOT re-run if already logged.

This is useful when:

- Testing one migration
- Developing incrementally
- Debugging schema issues

---

### 3️⃣ Roll Back Last Batch

```bash
php dgz migrate:rollback
```

**What This Does**

- Finds the highest batch number in `dgz_migrations`.
- Retrieves all migrations in that batch.
- Runs their `down()` methods in reverse order.
- Deletes those migration records from `dgz_migrations`.

**Example**

If your `dgz_migrations` table contains:

| migration | batch |
|---|---|
| create_users | 1 |
| create_posts | 1 |
| add_status | 2 |

Running:

```bash
php dgz migrate:rollback
```

Will:

- Roll back `add_status`
- Remove it from table
- Leave batch 1 intact

---

### 4️⃣ Reset All Migrations

```bash
php dgz migrate:reset
```

**What This Does**

- Rolls back ALL batches.
- Calls `down()` on every migration.
- Clears `dgz_migrations`.

This returns the database to a clean state.

Infrastructure tables remain intact.

---

### 5️⃣ Fresh Rebuild (Drop & Re-run)

```bash
php dgz migrate:fresh
```

**What This Does**

- Drops all non-infrastructure tables.
- Clears migration log.
- Runs all migrations from scratch.

It uses:

```php
MigrationRepository->dropAllNonInfrastructureTables()
```

Protected tables:

```
dgz_migrations
dgz_migration_locks
```

This is extremely useful during development.

---

### 6️⃣ Migration Status

```bash
php dgz migrate:status
```

Shows:

- All migration files
- Whether they have run
- Batch numbers

This reads from:

```
dgz_migrations
```

---

### 7️⃣ How Batching Works (Clarified)

Each time you run:

```bash
php dgz migrate
```

All new migrations are grouped into ONE new batch.

Batch numbers increment automatically.

Rollback removes only the latest batch.

This gives safe incremental rollbacks.

---

### 8️⃣ What Happens If Migration Fails Midway?

Because Dorguzen:

- Uses locking
- Logs only after execution

If a migration throws an exception:

- Execution stops
- Lock is released
- Migration is NOT logged
- It can be safely rerun

This protects schema integrity.

---

### 9️⃣ Recommended Developer Workflow

Creating a new table

```bash
php dgz make:migration create_orders_table
```

Edit file in:

```
database/migrations/
```

Then:

```bash
php dgz migrate
```

Undo last change

```bash
php dgz migrate:rollback
```

Rebuild entire schema

```bash
php dgz migrate:fresh
```

---

### 🔟 Production Best Practice

In production environments:

- Always run `php dgz migrate` during deployment.
- Never use `migrate:fresh`.
- Always ensure backups exist before rollback.
- Lock system ensures safe single-process execution.

---

### Final Summary of Commands

| Command | Purpose |
|---|---|
| `php dgz migrate` | Run all pending migrations |
| `php dgz migrate --file=...` | Run specific migration |
| `php dgz migrate:rollback` | Roll back last batch |
| `php dgz migrate:reset` | Roll back all batches |
| `php dgz migrate:fresh` | Drop all tables & re-run |
| `php dgz migrate:status` | Show migration state |
