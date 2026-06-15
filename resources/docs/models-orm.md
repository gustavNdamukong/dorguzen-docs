# Models and the ORM

Models in Dorguzen represent database tables. They extend `DGZ_Model` (`core/DGZ_Model.php`) and map one-to-one with a table.

---

## Anatomy of a Model

```php
namespace Dorguzen\Models;

use Dorguzen\Config\Config;
use Dorguzen\Core\DGZ_Model;

class News extends DGZ_Model
{
    protected $_columns = [];  // auto-populated from DB schema at runtime
    protected $data     = [];  // field values live here

    // Optional overrides — omit to use DGZ conventions
    protected $id = 'news_id';         // default: tableName_id
    // protected string $table = 'news'; // default: lcfirst(ClassName)

    protected $_hasChild  = [];
    protected $_hasParent = [];

    public function __construct(Config $config)
    {
        parent::__construct($config);
    }
}
```

---

## Naming Conventions

| Thing | Convention | Example |
|---|---|---|
| Model class | Uppercase first letter | `News`, `Users`, `BlogPost` |
| Table name | `lcfirst(ClassName)` | `news`, `users`, `blogPost` |
| Primary key | `tableName_id` | `news_id`, `users_id` |
| Field names | Prefixed with table name | `news_title`, `news_created` |

Declare overrides explicitly if your schema differs:

```php
protected string $table = 'app_news';
protected $id = 'article_id';
```

---

## Schema Auto-Loading

You never declare `$_columns` manually. On the first ORM call, `DGZ_Model` introspects the table schema and populates `$_columns` with `[fieldName => bindType]`. The result is cached statically for the rest of the request.

---

## Reading Records

```php
// All records (auto-orders by tableName_name if the column exists)
$news->getAll();
$news->getAll('news_created DESC');

// By primary key
$news->getById(5);

// By name field (tableName_name convention)
$news->getByName('My Article');

// Flexible WHERE query
$news->selectWhere(
    ['news_title', 'news_status'],       // columns (empty = all)
    ['news_status' => 'published'],      // WHERE criteria
    'ORDER BY news_created DESC'         // optional ORDER BY
);

// Select specific columns with optional WHERE and ordering
$news->selectOnly(
    ['news_id', 'news_title'],
    ['news_status' => 'published'],
    'news_created',
    'DESC'
);

// Count
$news->getCount();

// Paginated
$news->getPaginated($startOffset, $perPage);

// Raw SQL
$news->query("SELECT * FROM news WHERE news_status = ?", ['published']);
```

---

## Writing Records

### Insert

```php
// Property assignment + save() — use a fresh container instance
$news = container(News::class);
$news->news_title   = 'Breaking News';
$news->news_status  = 'published';
$news->news_created = $news->timeNow();
$newId = $news->save();   // returns new insert ID

// Array + insert()
$newId = $news->insert([
    'news_title'   => 'Breaking News',
    'news_status'  => 'published',
    'news_created' => $news->timeNow(),
]);
// Returns: insert ID | '1062' for duplicate | false on failure
```

### Update

```php
// Property assignment + update()
$news = container(News::class);
$news->news_status = 'draft';
$news->update(['news_id' => 5]);

// Array + updateObject()
$news->updateObject(
    ['news_status' => 'draft', 'news_title' => 'Updated'],
    ['news_id' => 5]
);
```

### Delete

```php
$news->deleteById('5');
$news->deleteWhere(['news_id' => 5]);  // also cascades to child models
```

---

## Write vs Read — Instance Rule

**Reads** are safe on the injected singleton — they do not mutate `$data`:

```php
$rows = $this->news->selectWhere([], ['news_status' => 'published']);
```

**Writes using property assignment** require a fresh instance to avoid polluting the singleton:

```php
$news = container(News::class);  // fresh instance
$news->news_status = 'draft';
$news->update(['news_id' => $id]);
```

**Raw SQL writes** are safe on the singleton since they do not touch `$data`:

```php
$this->news->query("DELETE FROM news WHERE news_id = ?", [$id]);
```

---

## Relationships

```php
protected $_hasChild = [
    \Dorguzen\Modules\Blog\Models\BlogComment::class => 'blogpost_id',
];

protected $_hasParent = [
    \Dorguzen\Models\Users::class => 'news_author_id',
];
```

After loading a record with `loadData($id)`, call the related class name as a method:

```php
$post->loadData(10);
$comments = $post->blogcomment();  // all BlogComments where blogpost_id = 10
$author   = $post->users();        // parent Users row
```

`deleteWhere()` automatically deletes child records before the parent when `$_hasChild` is set.

---

## Fetch Before Delete

Always fetch data you need for notifications **before** deleting:

```php
// Correct
$item  = $this->news->getById($id);
$email = $item['news_author_email'];
$this->news->deleteWhere(['news_id' => $id]);

// Wrong — record is already gone
$this->news->deleteWhere(['news_id' => $id]);
$item = $this->news->getById($id);  // returns false
```

---

## Utility Methods

```php
$news->timeNow()         // date("Y-m-d H:i:s") — use for timestamps
$news->getTable()        // resolved table name ('news')
$news->getIdFieldName()  // resolved PK field name ('news_id')
```

---

## Registering a Model

Every model must be registered in `bootstrap/app.php`:

```php
$container->singleton(News::class, fn($c) => new News($c->get(Config::class)));
```

Models always receive `Config` as their only constructor argument.
