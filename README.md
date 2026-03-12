# FilterManagerBundle

[![CI](https://github.com/ericgansa/filter-manager-bundle/actions/workflows/ci.yml/badge.svg)](https://github.com/ericgansa/filter-manager-bundle/actions/workflows/ci.yml)
[![Coverage](https://img.shields.io/badge/coverage-100%25-brightgreen)](https://github.com/ericgansa/filter-manager-bundle/actions)
[![PHP](https://img.shields.io/badge/PHP-8.2%2B-blue)](https://php.net)
[![Symfony](https://img.shields.io/badge/Symfony-6.4%20%7C%207.x-black)](https://symfony.com)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)

Symfony bundle for **dynamic filtering, pagination and user-scoped queries** with Doctrine ORM.

Supports a friendly query string notation:

```
GET /api/articles?title[like]=Symfony&author.name=Alice&date[after]=2024-01-01&page=1&limit=20&scope=mine
```

---

## Requirements

- PHP 8.2+
- Symfony 6.4 or 7.x
- Doctrine ORM 2.15+ or 3.x

> **Security is optional.** The bundle works without `symfony/security-bundle`.
> When SecurityBundle is not installed, user-scoped filtering (`mine`/`others`) is silently disabled.

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

| Type | Example | Operator |
|---|---|---|
| Equality | `?name=Alice` | `=` |
| LIKE (inline) | `?name=%Alice%` | `LIKE` |
| LIKE (array) | `?name[like]=Alice` | `LIKE` |
| Greater than | `?age=>30` or `?age[after]=30` | `>` |
| Less than | `?age=<18` or `?age[before]=18` | `<` |
| Greater or equal | `?price[from]=10` | `>=` |
| Less or equal | `?price[to]=100` | `<=` |
| Range (inline) | `?age=18..65` | `BETWEEN` |
| Range (array) | `?age[between]=18..65` | `BETWEEN` |
| Relation field | `?user.name=Alice` or `?user->name=Alice` | `=` |
| Date | `?date[after]=2024-01-01` | `>` (DateTimeImmutable) |

---

## Migration from `App\libs\managers\FilterManager`

### What changed

| Original | Bundle |
|---|---|
| `namespace App\libs\managers` | `namespace EricGansa\FilterManagerBundle\Filter` |
| `namespace App\Repository` (trait) | `namespace EricGansa\FilterManagerBundle\Filter` |
| `Security` hardcoded in constructor | Optional via `SecurityUserResolverInterface` |
| `limit` unbounded | Capped at `max_limit` (default: 100) |
| Scopes `mine`/`others` hardcoded | Configurable via `filter_manager.scopes` |
| `applyScope` used hardcoded `user` field | Configurable via `filter_manager.scope_field` |

### Step-by-step migration

**Step 1** — Install the bundle:
```bash
composer require ericgansa/filter-manager-bundle
```

**Step 2** — Remove the old class:
```bash
rm src/libs/managers/FilterManager.php
rm src/Repository/FilterManagerTrait.php
```

**Step 3** — Update your repository imports:
```php
// Before
use App\Repository\FilterManagerTrait;

// After
use EricGansa\FilterManagerBundle\Filter\FilterManagerTrait;
```

**Step 4** — Update your controller/service injection:
```php
// Before
use App\libs\managers\FilterManager;

// After
use EricGansa\FilterManagerBundle\Filter\FilterManager;
```

**Step 5** — Create the configuration file `config/packages/filter_manager.yaml`
with your desired `max_limit` and scope names.

**Step 6** — If you had a `limit` above 100 in your tests or API calls,
update them or raise `max_limit` in the config.

### Behavioral differences

- **`limit` is now capped**: requests with `?limit=9999` will be silently capped to `max_limit`.
- **Scope on unauthenticated requests**: previously, calling without Security would crash. Now it silently ignores scope.
- **Scope field**: if your entity uses a field other than `user` for ownership, set `scope_field` in the config.

---

## Docker (local development)

```bash
# Build the container
docker compose build

# Install dependencies
docker compose run --rm php composer install

# Run tests with coverage
docker compose run --rm php vendor/bin/phpunit --colors=always

# Static analysis
docker compose run --rm php vendor/bin/phpstan analyse src --level=8
```

---

## License

MIT © [Eric Gansa](mailto:ericgansa02@gmail.com)
