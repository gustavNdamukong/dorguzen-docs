# Models & ORM

This section covers everything about database access in terms of SQL queries, database configuration, and database design and management.

- Learn the SQL (Structured Query Language) which is a global standard for communication with most database systems.
- What types of database systems Dorguzen supports, for example, RDBS (Relational database systems) like MySQL, PosgreSQL, etc, and if any noSQL database system like Neo4j is supported
- It also covers what APIs Dorguzen provides to use in connecting with your chosen database solution.

This section covers:

- Models
  - The `$_hasParent` and `$_hasChild` Properties
  - What these properties mean
  - The array format
  - Omitting the foreign key (convention over configuration)
  - Example — the Products model
  - Lazy loading related data
- The Dorguzen Migration System
  - Running Migrations (CLI Commands)
- Database Seeding
  - The Seeding Architecture
  - CLI Commands
  - Seeder Classes
  - Factories — Automatic Record Generation
  - Data Pools — Smart Fake Data
  - Factory Lifecycle Hooks
  - Unique Values
  - Environment Protection
  - How everything works together
  - When Should You Use Seeding?
- Dorguzen's support for multiple DB drivers
  - Architecture Overview
  - How database access works
  - Database Driver API Reference
- Neo4j Graph Database Support in Dorguzen

---

## Models

Dorguzen has an ORM system that follows the active record system where model classes are mapped to corresponding database table names. Here are some conventions to follow:

- The model class files live in `src\models`.

- All model classes must extend the parent model, which is `Dorguzen\Core\DGZ_Model`.

- The model class names should be identical with the database table names, with the only exception that the table names begin in lowercase. For example, if a model name is `Users`, the database table name should be `users`. If the model name is `BaseSettings`, the database table name should be `baseSettings`.

- Ideally, every database table name should have a corresponding model class of exactly the same name, with the table name beginning in lowercase. However, if the table name is different from its correspondent model class, you should make Dorguzen aware of this by giving the model class a property named `table`, and assigning it the value of the actual table name. For example if you have a `Customers` model, and its corresponding database table is named `custos`, instead of the conventional `customers`; you must give the class a `$table` property like this:

```php
protected $table = 'custos';
```

- Dorguzen expects you to give the primary key field of your tables the name `id`, or a name formed from the model name with a suffix of `_id`; for example the primary key field of the users table can be `id`, or `users_id`. If you do not follow this convention, then you should make Dorguzen aware of the primary key field name of your model's table by giving the model a property named `id`, and its value should be the table's primary key field. For example, do this in the Users model because the primary key field is not named to convention:

```php
protected $id = 'usersId';
```

---

### The $_hasParent and $_hasChild Properties

All model classes can optionally have two very important properties if you want to define the relationship between two models (or database tables). To do so, you should add the following two properties to your model; `$_hasParent` and `$_hasChild`, and they are arrays. These properties must be declared with the `protected` keyword (not `private`) so that Dorguzen can read them internally.

```php
protected $_hasParent = [];

protected $_hasChild = [];
```

---

### What these properties mean

Think of it like a family tree. If you have a Users table and a Posts table, and every post belongs to a user, then:
- From the Post's point of view, Users is its PARENT (a post belongs to a user).
- From the User's point of view, Posts is its CHILD (a user can have many posts).

`$_hasChild` tells Dorguzen: "These are the tables/models that have a foreign key pointing back to ME."
`$_hasParent` tells Dorguzen: "These are the tables/models that I have a foreign key pointing TO."

---

### The array format

Each entry in both arrays follows this format:

```php
ModelClass::class => 'foreign_key_field_name'
```

For `$_hasChild` — the key is the child model class, and the value is the name of the foreign key field on the CHILD's table that points back to this model:

```php
protected $_hasChild = [
    Posts::class    => 'post_author_id',   // the posts table has a field called 'post_author_id'
    Comments::class => 'comment_user_id',  // the comments table has a field called 'comment_user_id'
];
```

For `$_hasParent` — the key is the parent model class, and the value is the name of the foreign key field on THIS model's table that points to the parent:

```php
protected $_hasParent = [
    Department::class => 'user_department_id',  // this table has a field called 'user_department_id'
];
```

You may also use a plain string class name instead of the `::class` constant. Both of the following are accepted by Dorguzen:

```php
Posts::class => 'post_author_id'    // recommended — safer, IDE-friendly
'Posts'      => 'post_author_id'    // also works
```

---

### Omitting the foreign key (convention over configuration)

If you leave the foreign key value as an empty string `''`, Dorguzen will automatically work out what the foreign key field is called, based on a naming convention.

For `$_hasChild` entries: Dorguzen assumes the child's foreign key field is named after the PARENT class, all in lowercase, with `_id` added at the end.

> Example: if the parent model is named `Users`, Dorguzen will look for a field called `users_id` on the child's table.

For `$_hasParent` entries: Dorguzen assumes this model's foreign key field is named after the PARENT class, all in lowercase, with `_id` added at the end.

> Example: if the parent model is named `Department`, Dorguzen will look for a field called `department_id` on this model's table.

So this:

```php
Posts::class => ''
```

...is exactly the same as:

```php
Posts::class => 'users_id'
```

...if the current model is named `Users`. Only omit the FK value if your database column actually follows this convention. If it does not, always specify the FK field name explicitly.

---

### Example — the Products model

```php
protected $_hasParent = [
    Location::class => 'location_id',         // products table has a 'location_id' FK pointing to locations
];

protected $_hasChild = [
    Prods_to_categories::class => 'product_id',
    Product_images::class      => 'product_images_product_id',
    Product_videos::class      => 'product_videos_product_id',
];
```

The above tells Dorguzen that the products table references the locations table via its `location_id` field, and that three other tables each have their own foreign key field pointing back to the products table.

Here is the example format of a complete model class in Dorguzen:

```php
namespace Dorguzen\Models;

use Dorguzen\Config\Config;
use Dorguzen\Core\DGZ_Model as Model;

class Users extends Model {
    // add this only if the matching table is not the class name beginning in lowercase
    protected $table = 'users';

    // all models must have these two properties
    protected $_columns = [];
    protected $data = [];

    // optional — declare your PK field name if it does not follow the DGZ convention
    protected $id = 'users_id';

    // optional — for managing entity relationships (must be 'protected', not 'private')
    protected $_hasParent = [
        Department::class => 'users_department_id',
    ];

    protected $_hasChild = [
        Posts::class    => 'post_author_id',
        Comments::class => 'comment_user_id',
    ];

    public function __construct(?Config $config)
    {
        return parent::__construct($config);
    }
}
```

---

### Creating a Model (CLI)

Use the `make:model` command to generate a new model stub:

```
php dgz make:model Products
```

This creates: `src/models/Products.php`

You can also create the paired migration file at the same time by adding the `-m` option:

```
php dgz make:model Products -m
```

This creates both:

```
src/models/Products.php
database/migrations/YYYY_MM_DD_HHMMSS_create_products_table.php
```

The migration name defaults to `create_{model}_table` (lowercased). If you want a custom migration name, pass it as the value of `-m`:

```
php dgz make:model Products -m create_shop_products_table
```

The generated migration is a skeleton — it contains a placeholder `id()`, `string('name')`, and `timestamps()`. Edit it to define your actual table columns before running `php dgz migrate`.

---

### Lazy loading related data

Once you have set up `$_hasChild` and `$_hasParent` on your models, Dorguzen gives you a powerful ability called LAZY LOADING. This means you can fetch related records from another table by simply calling a method named after that related model — without writing any SQL or any extra query code yourself. Dorguzen handles everything in the background.

#### What is lazy loading?

"Lazy" in this context does not mean slow or careless. It means the related data is only fetched from the database at the exact moment you ask for it — not before. This is efficient because you only pay the cost of a database query when you actually need the data.

#### How to use it — step by step

Step 1: Load a specific record into your model using `loadData($id)`. This tells Dorguzen which record you are working with. It populates the model's internal data array with that record's fields and values, and returns the model instance itself so you can chain the next call directly.

```php
$user = container(Users::class)->loadData(5);
```

Step 2: Call the related model's name as a method on that model instance.

To fetch children (one-to-many); call the child model name as a method. This returns an ARRAY of all matching child records.

```php
$posts    = $user->posts();     // returns all posts belonging to user 5
$comments = $user->comments();  // returns all comments belonging to user 5
```

To fetch parents (many-to-one); call the parent model name as a method. This returns a single ARRAY representing the one parent record.

```php
$department = $user->department();  // returns the department this user belongs to
```

#### What is returned?

- A hasChild call (fetching children) always returns an ARRAY of rows. Each row is itself an associative array of column names and values. If no children exist, an empty array `[]` is returned — never false or null.

- A hasParent call (fetching a parent) returns a single associative array representing the one parent row, or false if no matching record was found.

#### The naming rule — how to name your method call

This is the most important thing to understand about lazy loading in Dorguzen. The method name you call must match the CLASS NAME of the related model. The comparison is CASE-INSENSITIVE, which means you have complete freedom in how you capitalise the method call.

Example: if your child model class is named `Posts`, all of the following will work:

```php
$user->posts()
$user->Posts()
$user->POSTS()
```

They all resolve to the same model because Dorguzen converts both the method name and the class name to lowercase before comparing them.

What about longer or unusual class names? Suppose your posts model is not simply called `Posts` but has the more unusual name `TheirCrazyPosts`. In this case, ALL of the following are equivalent and will work correctly:

```php
$user->theirCrazyPosts()     // matches 'TheirCrazyPosts' — recommended, most readable
$user->theircrazyposts()     // also matches — all lowercase is fine
$user->THEIRCRAZYPOSTS()     // also matches — all uppercase is fine too
$user->TheirCrazyPosts()     // also matches — identical capitalisation to the class name
```

The recommended style is to write the method name in camelCase (first letter lowercase, rest matching the class name), which is the standard PHP convention for method calls:

```php
$user->theirCrazyPosts()     // recommended
```

IMPORTANT: the method name must match the MODEL CLASS NAME, not the database table name. Dorguzen looks up the model class in `$_hasChild` or `$_hasParent` and then works out the table name from the model itself. So if your class is named `TheirCrazyPosts` but its table is named `posts`, you still call `$user->theirCrazyPosts()` — never `$user->posts()`.

#### What happens if you call a method that does not exist?

If you call a method on a model and the name does not match anything in `$_hasChild` or `$_hasParent`, Dorguzen will throw a `BadMethodCallException` with a clear message. This helps you catch typos immediately rather than silently returning empty data.

```php
$user->blahBlah();
// throws: BadMethodCallException: Call to undefined method Users::blahBlah()
```

#### What happens if you forget to call loadData() first?

If you try to lazy-load children without having first loaded a record, Dorguzen cannot know which record's children to fetch — it needs the primary key value of the current record. In this case it throws a `RuntimeException` with a message telling you exactly what to do.

```php
$user = container(Users::class);   // model is empty — no record loaded yet
$user->posts();
// throws: RuntimeException: Cannot lazy-load 'posts': no primary key value is loaded on
//         Dorguzen\Models\Users. Call loadData($id) first to load a record into the model.
```

#### Full working example

Imagine a blog application. The Users model declares Posts as a child, and the Posts model declares Users as a parent.

In the Users model:

```php
protected $_hasChild = [
    Posts::class => 'post_author_id',
];
```

In the Posts model:

```php
protected $_hasParent = [
    Users::class => 'post_author_id',
];
```

In your controller or service:

```php
// Load user with ID 3, then fetch all their posts
$user  = container(Users::class)->loadData(3);
$posts = $user->posts();

foreach ($posts as $post) {
    echo $post['post_title'];
}

// Load post with ID 12, then fetch its author
$post   = container(Posts::class)->loadData(12);
$author = $post->users();

echo $author['users_name'];
```

#### How Dorguzen resolves the call internally (for the curious)

When you call `$user->posts()`, Dorguzen follows these steps:

1. PHP detects that `posts()` is not a real defined method on the Users model.
2. PHP automatically calls the `__call()` magic method on DGZ_Model, passing `posts` as the name.
3. Dorguzen loops through the `$_hasChild` array on Users, extracting just the short class name from each key (e.g. `Posts` from `Dorguzen\Models\Posts`) and lowercases it.
4. It finds that `posts` matches `posts` (from `Posts::class` lowercased).
5. It reads the foreign key field name from the array value (e.g. `post_author_id`).
6. It reads the primary key value of the currently loaded user record from the model's data.
7. It resolves the Posts model instance from the DI container.
8. It runs: `SELECT * FROM posts WHERE post_author_id = {user's PK value}`
9. It returns the result as an array of rows.

The class resolution in step 7 supports both full class names (FQCNs like `Dorguzen\Models\Posts`) and plain short strings like `Posts` — Dorguzen will find the right registered class either way.

---

### Reading records

Once a model is mapped to its table, `DGZ_Model` gives you a set of ready-made read methods. None of these require you to write SQL.

```php
// All records — auto-orders by tableName_name if that column exists
$news->getAll();
$news->getAll('news_created DESC');   // pass your own ORDER BY clause

// By primary key — returns a single associative row, or false
$news->getById(5);

// By the tableName_name field (DGZ naming convention)
$news->getByName('My Article');

// Flexible WHERE query
$news->selectWhere(
    ['news_title', 'news_status'],       // columns to select (empty = all)
    ['news_status' => 'published'],      // WHERE criteria (ANDed together)
    'ORDER BY news_created DESC'         // optional raw ORDER BY clause
);

// Select specific columns, with optional WHERE, order field and direction
$news->selectOnly(
    ['news_id', 'news_title'],           // columns to select
    ['news_status' => 'published'],      // optional WHERE
    'news_created',                      // order field (defaults to first column)
    'DESC'                               // 'ASC' (default) or 'DESC'
);

// Total record count
$news->getCount();

// A paginated slice (LIMIT $start, $perPage)
$news->getPaginated($startOffset, $perPage);

// Raw parameterised SQL, when you need full control
$news->query("SELECT * FROM news WHERE news_status = ?", ['published']);
```

`getAll()` and `selectWhere()` return an array of associative rows (an empty array if nothing matches). `getById()` returns a single row or `false`. `getCount()` returns the integer total.

---

### Writing records

**Insert**

```php
// Property assignment + save() — use a fresh container instance (see the instance rule below)
$news = container(News::class);
$news->news_title   = 'Breaking News';
$news->news_status  = 'published';
$news->news_created = $news->timeNow();
$newId = $news->save();   // returns the new insert ID

// Array + insert()
$newId = $news->insert([
    'news_title'   => 'Breaking News',
    'news_status'  => 'published',
    'news_created' => $news->timeNow(),
]);
// Returns: the new insert ID | '1062' on a duplicate-key error | false on failure
```

**Update**

```php
// Property assignment + update() — pass the WHERE criteria
$news = container(News::class);
$news->news_status = 'draft';
$news->update(['news_id' => 5]);
// If you set $news->id on the object, you can call $news->update() with no argument

// Array + updateObject($data, $where)
$news->updateObject(
    ['news_status' => 'draft', 'news_title' => 'Updated'],
    ['news_id' => 5]
);
```

**Delete**

```php
$news->deleteById('5');                 // delete by primary-key value
$news->deleteWhere(['news_id' => 5]);   // delete by any criteria
```

`deleteWhere()` automatically deletes matching child records first when `$_hasChild` is defined on the model, so you do not orphan related rows. If a criteria field does not exist on the table it returns an explanatory string instead of running the query.

---

### Reads vs writes — the instance rule

This is an important practical rule. **Reads** are safe on the injected/shared singleton instance because they do not mutate the model's internal `$data`:

```php
$rows = $this->news->selectWhere([], ['news_status' => 'published']);
```

**Writes that use property assignment** require a *fresh* instance, because assigning to a property (`$news->news_status = 'draft'`) populates the shared object's `$data` and would pollute the singleton for later calls. Always resolve a new instance from the container for these:

```php
$news = container(News::class);   // fresh instance
$news->news_status = 'draft';
$news->update(['news_id' => $id]);
```

**Raw SQL writes** are safe on the shared singleton, since they pass values directly and never touch `$data`:

```php
$this->news->query("DELETE FROM news WHERE news_id = ?", [$id]);
```

---

### Fetch before delete

A record is gone once you delete it, so always read any data you still need (for example to send a notification e-mail) **before** the delete call, never after:

```php
// Correct — read first, then delete
$item  = $this->news->getById($id);
$email = $item['news_author_email'];
$this->news->deleteWhere(['news_id' => $id]);

// Wrong — the record is already gone
$this->news->deleteWhere(['news_id' => $id]);
$item = $this->news->getById($id);  // returns false
```

---

### Utility methods

`DGZ_Model` exposes a few helpers you will reach for often:

```php
$news->timeNow()         // current timestamp string, date("Y-m-d H:i:s") — use for created/updated fields
$news->getTable()        // the resolved table name (e.g. 'news')
$news->getIdFieldName()  // the resolved primary-key field name (e.g. 'news_id')
```

---

### Registering a model

Every model is resolved from the DI container, so each one must be registered as a singleton in `bootstrap/app.php`. Models always receive `Config` as their only constructor argument:

```php
$container->singleton(News::class, fn($c) => new News($c->get(Config::class)));
```

After registration you obtain instances anywhere with the `container()` helper:

```php
$news = container(News::class);
```

---

## The Dorguzen Migration System

The Dorguzen migration system is responsible for:

- Creating tables
- Modifying schema
- Tracking which migrations have already run
- Preventing concurrent migration execution
- Supporting rollbacks
- Resetting databases safely

It is composed of:

Infrastructure Tables:

```
dgz_migrations
dgz_migration_locks
```

Core Classes:

```
Blueprint
Schema
Migration
MigrationRepository
MigrationLockRepository
```

### ⚠️ Infrastructure Requirement

For the migration system to function, the following tables must already exist:

```
dgz_migrations
dgz_migration_locks
```

You do NOT create migration files for these tables. They are infrastructure tables created automatically by the migration system itself. They are protected and never dropped by normal reset operations.

### 1️⃣ The Infrastructure Tables

There are two: `dgz_migrations` and `dgz_migration_locks`. You do not have to create them. Dorguzen will create them for you. This is just an explanation of how they work.

**a) The migrations table**

```
dgz_migrations
```

This table tracks which migrations have already been executed.

Structure:

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

**b) The migrations_lock table**

```
dgz_migration_locks
```

This table prevents two migration processes from running simultaneously.

Structure:

```
id (INT PRIMARY KEY)
locked_at (DATETIME NULL DEFAULT NULL)
```

Only ONE row is ever used:

```
id = 1
```

The way it works is the system will record a migration is currently running here so it is not run by any other script. It deletes it from here when its done and marks the migration file as ran in the migrations table. Note carefully that this id field is not incremented, because everytime a record is inserted in here, its value will always be 1.

This is a database-level mutex.

### 2️⃣ Migration Execution Flow (Internal Mechanics)

When migrations are executed:

**Step 1 — Ensure Infrastructure Tables Exist**

Both repositories call:

```
ensureTableExists()
```

This guarantees that:

```
dgz_migrations exists
dgz_migration_locks exists
```

Again, these two tables will be created if they do not exist already, which means you could create them yourself, but you do not have to. It's recommended to let the system create them for you, as you may get the fields and their types wrong.

**Step 2 — Acquire Lock**

```
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

**Step 3 — Determine Which Migrations Have Run**

```
MigrationRepository->getRan()
```

This fetches:

```sql
SELECT migration FROM dgz_migrations
```

Any migration file already recorded here will NOT be re-executed.

**Step 4 — Run New Migrations**

For each new migration:
- Instantiate migration class
- The `up()` method of the migration will be called
- The SQL statements of the migration via `addStatement()` is collected
- Each of the SQL statements is executed
- The specific migration is then inserted into the `dgz_migrations` table, thereby logging it as ran

**Step 5 — Release Lock**

After execution:

```
MigrationLockRepository->release()
```

Which runs:

```sql
DELETE FROM dgz_migration_locks WHERE id = 1
```

The migration file that was being run is now unlocked again.

### 3️⃣ The Migration Base Class

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

Here is how they work: The `up()` and `down()` methods do NOT execute SQL directly. Instead, they do this to collect their SQL statements:

```php
$this->addStatement($sql);
```

The migration runner later executes the collected SQL statements.

This allows for:
- Controlled execution
- Logging
- the possibility of rollbacks
- Dry runs (future possibility)

### 4️⃣ The Schema Class

Schema (the class) is responsible for:
- converting Blueprint definitions into SQL.
- Creating Tables eg

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

**Dropping tables**

The Schema class is also responsible for dropping Tables. This happens in the `down()` method of the migration class. For example:

```php
public function down(): void
{
    // rollback
    $sql = $this->schema->dropIfExists('users');
    $this->addStatement($sql);
}
```

### 5️⃣ Blueprint — The Table DSL

Blueprint is your schema builder DSL.

It generates SQL for:

```sql
CREATE TABLE `table` (...)
ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
Column Types Available
```

How to generate database field schemas:

**Primary Key**

```php
$table->id();
```

Creates:

```sql
BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
```

You can customize it like this:

```php
$table->id('user_id');
```

Custom Primary Key

```php
$table->primaryKey('uuid');
```

Which will create:

```sql
VARCHAR(255) PRIMARY KEY
```

This will be useful for UUIDs

**Composite key patterns**

Foreign ID (No constraints yet)

```php
$table->foreignId('user_id');
```

Creates:

```sql
INT UNSIGNED
```

(Foreign key constraints may be added later.)

**String**

```php
$table->string('name');
$table->string('name', 100);
```

Creates:

```sql
VARCHAR(255)
VARCHAR(100)
```

**Integer**

```php
$table->integer('age');
$table->unsignedInteger('score');
```

**Decimal**

```php
$table->decimal('price', 10, 2);
```

Creates:

```sql
DECIMAL(10,2)
```

**Enum**

```php
$table->enum('status', ['pending', 'approved', 'rejected']);
```

Creates:

```sql
ENUM('pending','approved','rejected') NOT NULL
```

**Text Types**

```php
$table->text('bio');
$table->longText('content');
```

```
TEXT (64KB)
LONGTEXT (~5GB)
```

**Dates & Timestamps**

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

**Unique Index**

```php
$table->unique('email');
```

Adds:

```sql
UNIQUE (`email`)
```

### 6️⃣ Creating a Migration File

To create a migration, run this command:

```
php dgz make:migration create_users_table
```

This will generate a migration file stub for you to easily edit:

Tip: if you are creating a model and a migration together, you can do both in one command using `make:model` with the `-m` flag — see the "CREATING A MODEL (CLI)" section above.

```
php dgz make:model Products -m                       // auto-names the migration create_products_table
php dgz make:model Products -m create_products_table // same, but with an explicit migration name
```

```
database/migrations/YYYY_MM_DD_HHMMSS_create_users_table.php
```

Example Migration File

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

### 7️⃣ What Happens When You Run Migrations

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

### 8️⃣ Batches Explained

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

### 9️⃣ Rollbacks

When rolling back, the last batch is fetched for each migration in reverse order:

```
getLastBatchMigrations()
```

Call `down()`

This method
- Runs the SQL in that migration's `down()` method which drops the table that migration created in `up()`
- it then removes the entry of that migrations from the `dgz_migrations` table

### 🔟 Resetting Database

```
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

### 1️⃣1️⃣ Why the Lock System Matters

Without locks:

- Two developers could run migrations simultaneously
- Tables could partially create
- Schema corruption possible

With `dgz_migration_locks`:

- Only one migration process runs
- Guaranteed safe schema changes

### 1️⃣2️⃣ Key Design Decisions

Dorguzen migrations:

```
✔ Are SQL-first
✔ Are explicit
✔ Collect statements before execution
✔ Prevent concurrent execution
✔ Track batches
✔ Support full rollback
✔ Protect infrastructure tables
```

### 1️⃣3️⃣ Mental Model for Developers

When writing a migration:

You are NOT writing raw SQL.

You are:

- Using Blueprint DSL
- Generating SQL
- Adding statements
- Letting Dorguzen execute safely
- Logging migration

**Final Summary**

The Dorguzen Migration System provides:

- Infrastructure tracking (`dgz_migrations`)
- Concurrency safety (`dgz_migration_locks`)
- Blueprint DSL for table creation
- SQL collection system via Migration
- Safe batch-based rollback
- Full database reset options
- Production-safe locking

Developers only need to:

```
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

### 1️⃣ Run All Pending Migrations

```
php dgz migrate
```

**What This Does**

Ensures infrastructure tables exist:

```
dgz_migrations
dgz_migration_locks
```

Acquires migration lock.

Reads all files inside the directory:

```
database/migrations/
```

Compares them against:

```
dgz_migrations
```

Runs only migrations that have NOT been logged.

Logs each migration into `dgz_migrations`.

Releases the lock.

**Important**

If a migration already exists in `dgz_migrations`, it will NOT run again.

This prevents accidental duplicate execution.

### 2️⃣ Run a Specific Migration File

```
php dgz migrate --file=2025_01_01_000000_create_users_test_table.php
```

**What This Does**

Executes only the specified migration file.

Does NOT run others.

Still respects locking.

Still logs into `dgz_migrations`.

Will NOT re-run if already logged.

This is useful when:

- Testing one migration
- Developing incrementally
- Debugging schema issues

### 3️⃣ Roll Back Last Batch

```
php dgz migrate:rollback
```

**What This Does**

Finds the highest batch number in `dgz_migrations`.

Retrieves all migrations in that batch.

Runs their `down()` methods in reverse order.

Deletes those migration records from `dgz_migrations`.

**Example**

If your `dgz_migrations` table contains:

| migration | batch |
|---|---|
| create_users | 1 |
| create_posts | 1 |
| add_status | 2 |

Running:

```
php dgz migrate:rollback
```

Will:

- Roll back `add_status`
- Remove it from table
- Leave batch 1 intact

### 4️⃣ Reset All Migrations

```
php dgz migrate:reset
```

**What This Does**

Rolls back ALL batches.

Calls `down()` on every migration.

Clears `dgz_migrations`.

This returns the database to a clean state.

Infrastructure tables remain intact.

### 5️⃣ Fresh Rebuild (Drop & Re-run)

```
php dgz migrate:fresh
```

**What This Does**

Drops all non-infrastructure tables.

Clears migration log.

Runs all migrations from scratch.

It uses:

```
MigrationRepository->dropAllNonInfrastructureTables()
```

Protected tables:

```
dgz_migrations
dgz_migration_locks
```

This is extremely useful during development.

### 6️⃣ Migration Status

```
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

### 7️⃣ How Batching Works (Clarified)

Each time you run:

```
php dgz migrate
```

All new migrations are grouped into ONE new batch.

Batch numbers increment automatically.

Rollback removes only the latest batch.

This gives safe incremental rollbacks.

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

### 9️⃣ Recommended Developer Workflow

Creating a new table

```
php dgz make:migration create_orders_table
```

Edit file in:

```
database/migrations/
```

Then:

```
php dgz migrate
```

Undo last change

```
php dgz migrate:rollback
```

Rebuild entire schema

```
php dgz migrate:fresh
```

### 🔟 Production Best Practice

In production environments:

- Always run `php dgz migrate` during deployment.
- Never use `migrate:fresh`.
- Always ensure backups exist before rollback.
- Lock system ensures safe single-process execution.

**Final Summary of Commands**

| Command | Purpose |
|---|---|
| `php dgz migrate` | Run all pending migrations |
| `php dgz migrate --file=...` | Run specific migration |
| `php dgz migrate:rollback` | Roll back last batch |
| `php dgz migrate:reset` | Roll back all batches |
| `php dgz migrate:fresh` | Drop all tables & re-run |
| `php dgz migrate:status` | Show migration state |

---

## Database Seeding

### What Is Database Seeding?

Database seeding is the process of automatically filling your database with data.

Instead of manually inserting rows into your tables, Dorguzen allows you to:

- Generate realistic test data
- Populate demo environments
- Prepare development databases
- Recreate consistent test datasets

Think of it like planting seeds 🌱 — your database grows with useful content automatically.

### The Seeding Architecture (Simple Overview)

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

### CLI Commands

Everything starts from the command line.

Basic command

```
php dgz db:seed
```

This runs the main seeder class called:

```
DatabaseSeeder
```

**Run a Specific Seeder**

```
php dgz db:seed --class=UserSeeder
```

This runs only `UserSeeder`.

**Run All Seeders Explicitly**

```
php dgz db:seed --all
```

This forces execution of `DatabaseSeeder`.

**Preview Without Running (Pretend Mode)**

```
php dgz db:seed --pretend
```

This shows what would happen — but does not insert anything.

This is safe for testing.

**Force Seeding in Protected Environments**

```
php dgz db:seed --force
```

Some environments (like production) are protected.

`--force` overrides that safety check.

**Fresh Migration + Seed**

```
php dgz migrate:fresh --seed
```

This:

- Drops all tables
- Re-runs all migrations
- Seeds the database

Perfect for starting over.

### Seeder Classes

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

> "Create 50 users."

**The Main Seeder: DatabaseSeeder**

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

### Factories — Automatic Record Generation

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

This defines what a "Post" looks like.

When you run:

```php
PostFactory::new()->count(10)->create();
```

Dorguzen will generate 10 posts automatically.

### Data Pools — Smart Fake Data

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

**Available Pools**

Here are some examples of built-in pools:

```php
// Text
Pool::get('text.sentence', 6)
Pool::get('text.paragraph', 3)
Pool::get('text.text', 200)

// Names & Emails
Pool::get('name.full')
Pool::get('email', $name)

// Numbers
Pool::get('number.int', 1, 100)
Pool::get('number.float', 0, 100, 2)
Pool::get('number.numeric', 6)

// Dates
Pool::get('date.now')

// Status & Flags
Pool::get('status')
Pool::get('boolean')
```

This keeps your factories clean and readable.

### Factory Lifecycle Hooks

Factories support lifecycle hooks.

`beforeCreate()`

Modify attributes before inserting:

```php
protected function beforeCreate(array &$attributes): void
{
    $attributes['slug'] = strtolower(str_replace(' ', '-', $attributes['title']));
}
```

`afterCreate()`

Run logic after insertion:

```php
protected function afterCreate(array $attributes): void
{
    // attach relationships
}
```

### Unique Values

Factories support unique generation.

Example:

```php
$email = $this->unique('email', function () use ($name) {
    return Pool::get('email', $name);
});
```

This prevents duplicate values in the database.

### Environment Protection

Dorguzen protects important environments.

If seeding is attempted in a protected environment, it will throw an error unless:

```
--force
```

This prevents accidental data corruption.

### How everything works together

Here is how everything works together. When you run:

```
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

This all happens automatically when you run the seed command

```
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

### When Should You Use Seeding?

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

Dorguzen ships with a SuperAdminSeeder that creates the initial super admin account your application needs from day one. This seeder is registered in DatabaseSeeder and is safe to run at any time — it uses `INSERT IGNORE`, so running it more than once will never create a duplicate record.

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

```
# 1. Run all migrations first
php dgz migrate

# 2. Seed the database (creates the super admin account)
php dgz db:seed
```

Or, if you want to reset everything from scratch:

```
php dgz migrate:fresh --seed
```

The seeder is idempotent — it is safe to run it multiple times.

---

## User Roles in Dorguzen

Dorguzen handles roles through the `users_type` field in the users table. There are four built-in roles:

```
super_admin   The highest-privilege user. Has access to everything, including
              actions that are restricted from admin and admin_gen users. There
              should normally be only one super admin per application.

admin_gen     A general administrator. Has broad admin access but cannot perform
              super-admin-only actions.

admin         A standard administrator. Has admin panel access within the scope
              assigned to them (e.g. managing a specific location or section).

member        A regular registered user. Has access only to the member-facing
              dashboard and their own account features.
```

In views and controllers you can check the current user's role using the `Auth()` helper:

```php
Auth()->role()            // returns the users_type string, e.g. 'super_admin'
Auth()->isAdmin()         // true if admin, admin_gen, or super_admin
Auth()->check()           // true if the user is authenticated (any role)
Auth()->guest()           // true if the user is NOT authenticated
```

Example — restrict a block of UI to super admins only:

```php
<?php if (Auth()->role() === 'super_admin'): ?>
    <a href="...">Delete User</a>
<?php endif; ?>
```

Roles are not enforced by a separate permissions table — they are a simple string value that your controllers and views inspect. This keeps the system lightweight and easy to extend if you need to add custom roles in the future.

---

## Dorguzen's support for multiple DB drivers

SQLite Support & Swappable Database Drivers. Dorguzen provides a fully swappable database layer. Developers can switch between supported database engines without changing application code — only configuration.

As of v1, Dorguzen officially supports:

```
✅ MySQLi (native MySQL driver)
✅ PDO (MySQL via PDO)
✅ SQLite (via PDO)
✅ PostgreSQL
```

This is how the current DB abstraction in Dorguzen works with the four drivers; Mysqli, SQLite, PDO and Postgres.

Here are the key files involved. They are 8 in number:

```
DGZ_DB_Singleton.php (main glue)
DGZ_DBDriverInterface.php (the contract)

DGZ_MySQLiDriver.php
DGZ_PDODriver.php

DGZ_SQLiteDriver.php
DGZ_PostgresDriver.php

DGZ_DBAdapter.php (the bridge)
DGZ_Model.php (parent ORM model)
```

First of all, all Dorguzen (dgz) models gain DB access by extending the parent model DGZ_Model. Whenever DGZ_Model's `connect()` is called (which eventually all models will call if they need access to the DB), DGZ_Model pulls an instance of DGZ_DB_Singleton

```php
protected function connect()
{
    $db = DGZ_DB_Singleton::getInstance();
    $this->hydrateSchemaIfNeeded();
    return $db;
}
```

Here is what the `hydrateSchemaIfNeeded()` model looks like, including the chain of other methods being called in the process:

```php
protected function hydrateSchemaIfNeeded(): void
{
    $class = static::class;

    if (!isset(self::$schemaCache[$class])) {
        $schema = $this->loadSchemaFromDatabase();
        self::$schemaCache[$class] = $schema;
    }

    $this->_columns = self::$schemaCache[$class];

    $this->validateModelData();
    $this->applyNullDefaults();
}


public function loadSchemaFromDatabase()
{
    $db = DGZ_DB_Singleton::getInstance();

    $table = $this->getTable();

    // Load schema
    $schemaQuery = 'DESCRIBE ' . lcfirst($table);
    $columns = $db->query($schemaQuery);

    if (empty($columns)) {
        throw new RuntimeException(
            "ORM schema load failed for table '{$table}' (" . static::class . ")"
        );
    }

    $schema = [];

    foreach ($columns as $column) {
        $val = 's';
        if (preg_match('/int/', $column['Type'])) $val = 'i';
        if (preg_match('/decimal|float/', $column['Type'])) $val = 'd';

        $schema[$column['Field']] = $val;
    }

    return $schema;
}


protected function validateModelData(): void
{
    foreach ($this->data as $setDataKey => $setDataValue)
    {
        if (!array_key_exists($setDataKey, $this->_columns))
        {
            throw new RuntimeException(
                "Invalid ORM field '{$setDataKey}' on model " . static::class
            );
        }
    }
}

protected function applyNullDefaults(): void
{
    foreach ($this->_columns as $column => $_type) {
        if (!array_key_exists($column, $this->data)) {
            $this->data[$column] = null;
        }
    }
}
```

DGZ_DB_Singleton is the glue point between your application and the DB drivers. It checks the application's config for the defined DB driver type (DB driver) and connection credentials. It then loads the relevant driver instance which will be either one of these:

```
DGZ_MySQLiDriver
DGZ_PDODriver
DGZ_SQLiteDriver
DGZ_PostgresDriver
```

while passing the given credentials to the constructor of that DB driver. It then passes that driver object into an instance of DGZ_DBAdapter, which stores that DB driver on its `driver` property. It then returns the DGZ_DBAdapter instance back to `DGZ_Model->connect()` the caller. The current or active DB driver is therefore stored in the `$driver` property of DGZ_DBAdapter, the class that DGZ_Model and all its children will now be using. That is it, in a nutshell.

It's worthy to note, that all the DB drivers; currently DGZ_MySQLiDriver, DGZ_PDODriver, DGZ_SQLiteDriver and DGZ_PostgresDriver implement the DGZ_DBDriverInterface which is the contract defining all the methods these drivers must implement.

The DB connection and queries that DGZ_Model now uses will all go through DGZ_DBAdapter.

But here is where the magic happens; all these methods implemented by the DB drivers, which are defined by the contract (DGZ_DBDriverInterface) are also implemented by DGZ_DBAdapter. This is in order that, whenever a model in your application calls any of these methods, the DGZ_DBAdapter has the method, but internally, it passes the query to the active DB driver by calling that same method on that driver.

---

### 1. Architecture Overview

Dorguzen's database layer is built around 6 core files:

```
DGZ_DB_Singleton.php        (Main glue)
DGZ_DBDriverInterface.php   (Contract)
DGZ_MySQLiDriver.php
DGZ_PDODriver.php
DGZ_SQLiteDriver.php
DGZ_PostgresDriver.php
DGZ_DBAdapter.php           (Bridge)
DGZ_Model.php               (Parent ORM model)
```

### 2. How Database Access Works

**Step 1 — Models Extend DGZ_Model**

All models gain database access by extending DGZ_Model. When a model needs DB access:

```php
protected function connect()
{
    $db = DGZ_DB_Singleton::getInstance();
    $this->hydrateSchemaIfNeeded();
    return $db;
}
```

**Step 2 — Schema Hydration (Automatic ORM Mapping)**

Before queries execute, Dorguzen introspects the database table:

```php
protected function hydrateSchemaIfNeeded(): void
```

This calls:

```
loadSchemaFromDatabase()
```

Which:

- Loads table schema
- Detects column types
- Caches the schema
- Validates model fields
- Applies null defaults

This guarantees:

- No invalid fields can be inserted
- ORM and DB stay synchronized
- Columns are auto-typed
- Schema is cached per model class for performance.

**Step 3 — The Singleton Glue Layer**

DGZ_DB_Singleton:

- Reads `.env` credentials
- Detects `DB_CONNECTION`
- Instantiates the correct driver
- Wraps it in DGZ_DBAdapter
- Returns the adapter

Example flow:

```
Model → DGZ_DB_Singleton
      → Creates Driver
      → Wraps in Adapter
      → Returns Adapter
```

**Step 4 — The Adapter Pattern**

DGZ_DBAdapter is the bridge between:

- Your application
- The active database driver

All drivers implement:

```
DGZ_DBDriverInterface
```

The adapter implements the same methods and internally forwards calls to the active driver:

```php
public function query(string $sql, array $params = [])
{
    return $this->driver->query($sql, $params);
}
```

This means:

- Models never know which DB is active
- Drivers can be swapped without code changes

### 3. Official Driver Support

| Driver | Status | Notes |
|---|---|---|
| MySQLi | ✅ | Fully Supported |
| PDO (MySQL) | ✅ | Fully Supported |
| SQLite | ✅ | Fully Supported |
| PostgreSQL | ✅ | Fully Supported |

### 4. query() vs execute() (Important Design Rule)

Dorguzen intentionally separates read and write operations.

**query()**

- Recommended for SELECT queries ONLY
- Returns array of rows
- Does NOT track affected rows or last insert ID

Example:

```php
$rows = $db->query("SELECT * FROM users WHERE id = ?", [$id]);
```

**execute()**

- Recommended for INSERT / UPDATE / DELETE
- Returns boolean
- It tracks:
  - affected rows
  - The last insert ID (if applicable)

Example:

```php
$success = $db->execute(
    "UPDATE users SET name = ? WHERE id = ?",
    [$name, $id]
);
```

**Why This Design?**

- Clean separation of concerns
- No SQL parsing inside drivers
- Predictable behavior
- Easier maintenance

### 5. Getting the Last Inserted ID

After an INSERT using `execute()`:

```php
$db->execute(
    "INSERT INTO users (name, email) VALUES (?, ?)",
    [$name, $email]
);

$id = $db->lastInsertId();
```

Works for:

- MySQLi
- PDO
- SQLite
- PostgreSQL (via RETURNING)

### 6. Environment Configuration (.env Setup)

Dorguzen uses `.env` to determine which database engine to load.

**Using MySQLi**

```
DB_CONNECTION=mysqli

DB_HOST=127.0.0.1
DB_USERNAME=your_username
DB_PASSWORD=your_password
DB_DATABASE=your_database
DB_PORT=3306
```

Requirements:

- MySQL server installed (MAMP, XAMPP, Docker, etc.)
- mysqli extension enabled

**Using PDO (MySQL via PDO)**

```
DB_CONNECTION=pdo
DB_HOST=127.0.0.1
DB_USERNAME=your_username
DB_PASSWORD=your_password
DB_DATABASE=your_database
DB_PORT=3306
```

Requirements:

- MySQL server
- pdo_mysql extension enabled

**Using SQLite**

```
DB_CONNECTION=sqlite
DB_SQLITE_PATH=/absolute/path/to/storage/database.sqlite
```

Important:

SQLite uses a file path, NOT host/user/password. The file must exist before connecting.

Create the file:

```
touch storage/database.sqlite
chmod 644 storage/database.sqlite
```

If in the Dorguzen testing environment, the setting in `.env.testing` should be like this:

```
DB_CONNECTION=sqlite
DB_SQLITE_PATH=:memory:
```

`:memory:` creates a temporary in-memory database for tests. In this testing setup, no MySQL server is required.

**Using PostgreSQL**

```
DB_CONNECTION=pgsql

DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=your_database
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

Requirements:

- PostgreSQL installed locally or via Docker
- pdo_pgsql extension enabled

Example Docker setup:

```
docker run --name pg-dorguzen \
  -e POSTGRES_PASSWORD=secret \
  -p 5432:5432 -d postgres
```

### 7. Switching Drivers

You would switch DB drivers to change the active database engine. That is what this line in all the driver types above have been doing:

```
DB_CONNECTION=
```

The next thing is to update the `.env` file. Then restart your application. That is it; there are no code changes required.

This is the power of the Adapter + Interface pattern.

### 8. Schema Introspection Differences

Dorguzen automatically maps your model to its database table using its built-in Object Relational Mapper (ORM) that works with the currently active database driver once you load your application.

All drivers normalize column keys to:

```
Field
Type
```

So the ORM behaves consistently across engines.

### 9. Primary Key Flexibility

Dorguzen allows models to use any name for the primary key field of any table linked to a model.

But the default recommended name for the primary key field is:

```
id
```

If different, define it on the model by giving the model an `id` (string) field, with its value the name of your model's primary key field.

Drivers dynamically detect primary key fields where supported.

### 10. Design Philosophy

Dorguzen enforces:

An MVC structure whereby
- the DB logic lives in Models
- Controllers handle data logic
- Views contain no database access
- The database engine is configurable infrastructure

This promotes:
- Clean architecture
- Testability
- Portability
- Engine independence

### 11. Testing Strategy

Once more, it is recommended:

- to use SQLite `:memory:` for automated tests
- To use MySQL for production

The following is the documentation of the public API of the database layer.

---

## Database Driver API Reference

All Dorguzen database drivers implement:

```
DGZ_DBDriverInterface
```

These methods are accessed through the DGZ_DBAdapter, which is what your models receive when calling:

```php
$db = $this->connect();
```

You never interact with drivers directly — always through the adapter.

### 1️⃣ getTableSchema(string $table): array

**Purpose**

Used internally by the ORM to introspect table structure and map model fields.

**Parameters**

| Parameter | Type | Description |
|---|---|---|
| `$table` | string | Database table name |

**Returns an array**

An array describing table columns.

**Used By**

- DGZ_Model::loadSchemaFromDatabase()
- ORM schema hydration system

**Should Developers Use It?**

No. This is an internal ORM method.

### 2️⃣ connect()

**Purpose**

Returns the raw underlying database connection object.

for either of the following depending on the currently active driver in use:

- MySQLi driver → mysqli
- PDO driver → PDO
- SQLite → PDO
- PostgreSQL → PDO

**When To Use**

Only for advanced scenarios (transactions, vendor-specific features).

Most applications should NOT need this.

### 3️⃣ prepare(string $query)

**Purpose**

Prepares a raw SQL statement.

**Parameters**

| Parameter | Type | Description |
|---|---|---|
| `$query` | string | SQL statement |

**Returns**

Driver-specific statement object.

**When To Use**

Rarely needed directly. Use `query()` or `execute()` instead.

### 4️⃣ query(string $sql, array $params = []): array

**Purpose**

Execute SELECT queries.

**Design Rule**

Recommended for SELECT statements ONLY.

**Parameters**

| Parameter | Type | Description |
|---|---|---|
| `$sql` | string | SQL SELECT statement |
| `$params` | array | Bound parameters |

**Returns**

It returns an array of associative rows.

**Example**

```php
$db = $this->connect();

$users = $db->query(
    "SELECT * FROM users WHERE status = ?",
    ['active']
);
```

Returns

```php
[
    ['id' => 1, 'name' => 'John'],
    ['id' => 2, 'name' => 'Jane']
]
```

**Why Not Use For INSERT?**

Because `query()` is designed for returning rows, not mutation tracking.

### 5️⃣ execute(string $sql, array $params = []): bool

**Purpose**

Execute INSERT, UPDATE, DELETE queries.

**Design Rule**

Recommended for mutation queries ONLY.

**Parameters**

| Parameter | Type | Description |
|---|---|---|
| `$sql` | string | SQL mutation statement |
| `$params` | array | Bound parameters |

**Returns**

It returns a boolean (True if execution succeeded, or False otherwise).

**Example — Insert**

```php
$db = $this->connect();

$db->execute(
    "INSERT INTO users (name, email) VALUES (?, ?)",
    [$name, $email]
);
```

**Example — Update**

```php
$db->execute(
    "UPDATE users SET name = ? WHERE id = ?",
    [$name, $id]
);
```

### 6️⃣ lastInsertId(): int|string|null

**Purpose**

It returns the last inserted primary key.

**Example**

```php
$db->execute(
    "INSERT INTO posts (title) VALUES (?)",
    [$title]
);

$id = $db->lastInsertId();
```

Works for:

```
MySQLi
PDO
SQLite
PostgreSQL (via RETURNING)
```

### 7️⃣ getAffectedRows(): int

**Purpose**

It returns number of rows affected by the last mutation query.

**Example**

```php
$db->execute(
    "UPDATE users SET status = ? WHERE status = ?",
    ['inactive', 'active']
);

$count = $db->getAffectedRows();
```

Returns a number like

```
3
```

Very useful for update/delete verification.

### 8️⃣ numRows($result): int

**Purpose**

Returns number of rows in a result set.

This is not reliable for SELECT in PDO-based drivers.

**Example**

```php
$rows = $db->query("SELECT * FROM users");
```

Normally you would simply do:

```php
$count = count($rows);
```

Recommended usage: avoid unless working with low-level result objects.

### 9️⃣ getPrimaryKeyField(string $table): ?string

**Purpose:**

It returns the name of a table's primary key column.

**Example**

```php
$pk = $db->getPrimaryKeyField('users');
```

Returns:

It returns a string of what the primary key field of the model is, for example:

```
id
```

Used internally by ORM and Postgres RETURNING logic.

### 🔟 prepareInsertOrUpdate(array $data, array $passwordFields, string $type = 'insert')

**Purpose:**

It builds SQL fragments for INSERT and UPDATE queries. It is used internally by DGZ_Model::save() and updateObject().

**Parameters**

| Parameter | Description |
|---|---|
| `$data` | Field-value pairs |
| `$passwordFields` | Fields requiring encryption |
| `$type` | insert or update |

**Returns**

It returns an array like this:

```php
[$fields, $placeholders, $values]
```

Example internal usage:

```php
list($fields, $placeholders, $values) =
    $db->prepareInsertOrUpdate($data, ['password'], 'insert');
```

Developers do NOT call this directly.

### 1️⃣1️⃣ encryptPasswordCondition(string $field): string

**Purpose:**

It builds encrypted WHERE condition for password comparisons (MySQL AES_ENCRYPT).

Example internal usage:

```sql
WHERE password = AES_ENCRYPT(?, ?)
```

It is however only relevant for the MySQL driver.

**Practical Usage Example Inside a Model:**

```php
class User extends DGZ_Model
{
    protected string $table = 'users';

    public function findActiveUsers()
    {
        $db = $this->connect();

        return $db->query(
            "SELECT * FROM users WHERE status = ?",
            ['active']
        );
    }

    public function deactivateUser(int $id): bool
    {
        $db = $this->connect();

        $db->execute(
            "UPDATE users SET status = ? WHERE id = ?",
            ['inactive', $id]
        );

        return $db->getAffectedRows() > 0;
    }
}
```

**Internal vs Public Methods Summary**

| Method | Public Use | Internal |
|---|---|---|
| query | ✅ | Yes |
| execute | ✅ | Yes |
| lastInsertId | ✅ | Yes |
| getAffectedRows | ✅ | Yes |
| connect (Advanced) | | |
| getTableSchema | ❌ | ORM only |
| prepareInsertOrUpdate | ❌ | ORM only |
| encryptPasswordCondition | ❌ | Driver only |

Dorguzen enforces:

- Clear separation between read and write queries
- There is no SQL parsing in drivers
- Driver swappability via adapter
- ORM-driven schema validation
- Environment-based DB switching

---

## Neo4j Graph Database Support in Dorguzen

Dorguzen v1 introduces optional support for Neo4j, a popular graph database, allowing developers to harness the full power of Cypher queries without limiting flexibility or imposing an ORM-style abstraction. This section explains how Neo4j is integrated, how to use it, and the design philosophy behind the implementation.

### Philosophy

By design, Dorguzen does not abstract graphs into models or attempt to rewrite the Cypher Query Language. Instead, the framework exposes a Neo4j client directly to the developer. This approach allows developers to:

- Write any Cypher query they need.
- Fully leverage graph-specific capabilities such as relationships, traversals, pathfinding, aggregations, and APOC procedures.
- Use transactions where needed.
- Avoid learning a proprietary or framework-specific query builder.

This ensures maximum flexibility while keeping Dorguzen lightweight and non-opinionated regarding graph modeling.

### Installation

Neo4j support is optional. To install the Neo4j PHP client via Composer:

```
composer require laudis/neo4j-php-client:^3.4
```

In Dorguzen's `composer.json`, Neo4j is marked in the `"suggest"` block:

```json
"suggest": {
    "laudis/neo4j-php-client": "Required for Neo4j graph support. Install ^3.4"
}
```

If Neo4j is not needed for a project, it can be completely omitted without affecting other Dorguzen functionality.

### Configuration

Add Neo4j credentials in your `.env` file:

```
NEO4J_URI=bolt://127.0.0.1:7687
NEO4J_USERNAME=neo4j
NEO4J_PASSWORD=your_password_here
```

Capture them in `configs/database.php`:

```php
'Neo4jCredentials' => [
    'uri' => env('NEO4J_URI'),
    'username' => env('NEO4J_USERNAME'),
    'password' => env('NEO4J_PASSWORD'),
],
```

### Core Integration

Dorguzen provides a thin wrapper client at:

```
core/database/graph/DGZ_Neo4jClient.php
```

This class:
- Connects to Neo4j using the official `laudis/neo4j-php-client`.
- Provides a `run()` method for executing Cypher queries and returning normalized array results.
- Supports transactions via a `transaction()` method for safe write operations.
- Normalizes results, including Node and Relationship objects, into plain PHP arrays for easy handling.

**Example Usage**

```php
use Dorguzen\Core\Database\Graph\DGZ_Neo4jClient;

$config = container(Config::class);
$neo4jConn = $config->getConfig('database.Neo4jCredentials');

$neo = new DGZ_Neo4jClient($neo4jConn);


// Simple query
$result = $neo->run('RETURN 1 AS test');
print_r($result);


// Create a new node
$neo->run('CREATE (u:User {name: $name}) RETURN u', ['name' => 'Alice']);


// Update a node
$neo->run(
    'MATCH (u:User {name: $name}) SET u.updated = true RETURN u',
    ['name' => 'Alice']
);


// Transaction example
$neo->transaction(function ($tx) {
    $tx->run('CREATE (n:Task {title: "Finish Dorguzen"})');
});
```

### Optional and Modular Design

Optional installation: Neo4j support is not required for Dorguzen to function.

Easy removal: If installed but not needed, the package can be removed from Composer and the DGZ_Neo4jClient class will not be referenced, preventing errors.

No changes to MVC: Controllers and other models continue to operate normally. Developers simply use the Neo4j client when graph operations are required.

### Summary

Dorguzen exposes Neo4j via a thin client wrapper, preserving full Cypher flexibility. No ORM abstraction is imposed—developers can fully leverage graph capabilities. Installation is optional, modular, and easily removable. Transactions and result normalization are supported. Works seamlessly alongside other relational DB drivers in a hybrid architecture. This approach gives developers freedom and power while keeping Dorguzen lightweight and modular, aligning perfectly with the framework's philosophy.
