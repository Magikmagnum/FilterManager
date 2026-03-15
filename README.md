# FilterManagerBundle

Symfony bundle for dynamic filtering, pagination and user-scoped queries with Doctrine ORM.

Supports a friendly query string notation:

```
GET /api/articles?title[like]=Symfony&author->name=Alice&date[after]=2024-01-01&page=1&limit=20&scope=mine
```

## Requirements

* PHP 8.2+
* Symfony 6.4 or 7.x
* Doctrine ORM 2.15+ or 3.x

Security is optional. The bundle works without `symfony/security-bundle`. When SecurityBundle is not installed, user-scoped filtering (`mine`/`others`) is silently disabled.

---

## Installation

```bash
composer require ericgansa/filter-manager-bundle
```

Register the bundle in `config/bundles.php` (auto-registered via Symfony Flex):

```php
EricGansa\FilterManagerBundle\FilterManagerBundle::class => ['all' => true],
```

---

## Configuration

Create `config/packages/filter_manager.yaml`:

```yaml
filter_manager:
    # Maximum items per page — prevents ?limit=99999 abuse. Default: 100
    max_limit: 100

    # Entity field linking to the owner user. Default: 'user'
    scope_field: user

    # Query string scope names — fully customizable
    scopes:
        mine: 'mine'       # ?scope=mine   → only current user's items
        others: 'others'   # ?scope=others → exclude current user's items
        all: 'all'         # ?scope=all    → no user filter (default)
```

---

## Usage

### 1. In a Repository (via trait)

```php
use EricGansa\FilterManagerBundle\Filter\FilterManagerTrait;

class ArticleRepository extends ServiceEntityRepository
{
    use FilterManagerTrait;
}
```

### 2. In a Controller

```php
use EricGansa\FilterManagerBundle\Filter\FilterManager;

class ArticleController extends AbstractController
{
    public function __construct(private readonly FilterManager $filterManager) {}

    #[Route('/api/articles', methods: ['GET'])]
    public function index(Request $request, ArticleRepository $repository): JsonResponse
    {
        $articles = $this->filterManager->mapRequestToRepository(
            $request,
            $repository,
            'findByFilterManager'  // method from FilterManagerTrait
        );

        return $this->json($articles);
    }
}
```

### 3. Manual usage (without the trait)

```php
$qb = $repository->createQueryBuilder('e');

FilterManager::applyFiltersToQueryBuilder($qb, $filters, 'e');
FilterManager::applyPaginationToQueryBuilder($qb, $pagination, 'e');

// With configured scope (uses bundle config)
$filterManager->applyScopeWithConfig($qb, $scope, $user, 'e');
```

---

## Query String Reference

### Example Entity

The following examples are based on this `Article` entity:

```php
#[ORM\Entity(repositoryClass: ArticleRepository::class)]
class Article
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id;

    #[ORM\Column(type: Types::TEXT)]
    private string $description;

    #[ORM\ManyToOne(targetEntity: Label::class)]
    private Label $label;

    #[ORM\ManyToOne(targetEntity: User::class)]
    private User $user;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;
}
```

All query string examples in this section filter against this entity. The `label` relation points to a `Label` entity with its own `label` string field. The `user` field is used by the scope system to determine ownership.

---

### Equality

Filter results where a field exactly matches a value.

```
GET /api/articles?id=5
GET /api/articles?description=Gestion agile...
```

| Example | Result |
|---|---|
| `?id=5` | Returns the item whose `id` is exactly `5` |
| `?description=Gestion agile...` | Returns items with that exact description |

---

### LIKE (partial match)

Filter results where a field contains a substring. Two syntaxes are supported and produce identical results.

```
GET /api/articles?description=%Expert%
GET /api/articles?description[like]=Expert
```

| Syntax | Example | Notes |
|---|---|---|
| Inline (with `%`) | `?description=%Expert%` | Use `%` as wildcard directly in the value |
| Array | `?description[like]=Expert` | Cleaner syntax, no need for `%` |

---

### Greater Than

Filter results where a numeric (or date) field is strictly greater than a value. Two syntaxes are supported.

```
GET /api/articles?id=>5
GET /api/articles?id[after]=5
```

| Syntax | Example | Notes |
|---|---|---|
| Inline | `?id=>5` | Prefix value with `>` |
| Array | `?id[after]=5` | Uses the `[after]` key |

---

### Less Than

Filter results where a numeric (or date) field is strictly less than a value. Two syntaxes are supported.

```
GET /api/articles?id=<6
GET /api/articles?id[before]=6
```

| Syntax | Example | Notes |
|---|---|---|
| Inline | `?id=<6` | Prefix value with `<` |
| Array | `?id[before]=6` | Uses the `[before]` key |

---

### Greater Than or Equal

Filter results where a field is greater than or equal to a value.

```
GET /api/articles?id[from]=8
```

---

### Less Than or Equal

Filter results where a field is less than or equal to a value.

```
GET /api/articles?id[to]=5
```

---

### Range (BETWEEN)

Filter results where a field falls within an inclusive range. Two syntaxes are supported.

```
GET /api/articles?id=3..7
GET /api/articles?id[between]=3..7
```

| Syntax | Example | Notes |
|---|---|---|
| Inline | `?id=3..7` | Separate bounds with `..` |
| Array | `?id[between]=3..7` | Uses the `[between]` key |

---

### Relation Filtering

Filter on a field belonging to a related entity. Use the `->` notation to traverse the relation.

> ⚠️ **Important:** Always use `->` to separate the relation from the field name (e.g. `label->label`). **Do not use `.`** — PHP automatically converts `.` to `_` in query strings, which causes a Doctrine error.

```
GET /api/articles?label->label=Développeur PHP
GET /api/articles?label->label[like]=Développeur
```

| Example | Notes |
|---|---|
| `?label->label=Développeur PHP` | Exact match on the related field |
| `?label->label[like]=Développeur` | LIKE match on the related field |

---

### Date Filtering

Filter results by date using `[after]` and `[before]`. Values must be ISO 8601 date strings. The field must be of type `DateTimeImmutable` (or compatible).

```
GET /api/articles?createdAt[after]=2026-01-01
GET /api/articles?createdAt[before]=2020-01-01
```

| Example | Notes |
|---|---|
| `?createdAt[after]=2026-01-01` | Items created after January 1st 2026 |
| `?createdAt[before]=2020-01-01` | Items created before January 1st 2020 |

---

### Scope (User-based filtering)

Restrict results based on ownership. Requires `symfony/security-bundle` to be installed and a logged-in user. The entity must have a field pointing to the owner (configured via `scope_field`).

```
GET /api/articles?scope=mine
GET /api/articles?scope=others
GET /api/articles?scope=all
```

| Value | Behaviour |
|---|---|
| `mine` | Returns only items belonging to the currently authenticated user |
| `others` | Returns only items **not** belonging to the current user |
| `all` | No user filter applied (default behaviour) |

---

## Chaining Filters

All filters can be combined freely in a single query string. Each parameter is applied as an additional `AND` condition on the query.

```
GET /api/articles?label->label[like]=Développeur&id[from]=3&createdAt[after]=2026-01-01&scope=mine
```

This example returns items that:
- have a related label containing `"Développeur"`,
- have an `id` of `3` or more,
- were created after January 1st 2026,
- and belong to the currently authenticated user.

You can mix inline and array syntaxes freely:

```
GET /api/articles?id=>5&description[like]=Expert&scope=all
```

There is no limit to the number of filters that can be chained.

---

## License

MIT © Eric Gansa
