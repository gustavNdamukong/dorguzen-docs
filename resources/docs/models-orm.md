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
- Migrations — see the dedicated [Migrations](/dorguzen-docs/docs/migrations) page
- Database seeding — see the dedicated [Database Seeding](/dorguzen-docs/docs/seeding) page
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

> **Migrations** have their own page — see [Migrations](/dorguzen-docs/docs/migrations).

---

> **Database seeding** has its own page — see [Database Seeding](/dorguzen-docs/docs/seeding).

---

> See [Authentication](/dorguzen-docs/docs/authentication) for user roles and the Auth() helper.

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
