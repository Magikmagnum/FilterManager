<?php

declare(strict_types=1);

namespace EricGansa\FilterManagerBundle\Filter;

use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ObjectRepository;
use EricGansa\FilterManagerBundle\Contract\FilterManagerInterface;
use EricGansa\FilterManagerBundle\Contract\SecurityUserResolverInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Main FilterManager service.
 *
 * Handles dynamic filtering, pagination and user-scoped queries for Doctrine ORM repositories.
 *
 * Supported query string notation:
 *   Simple equality   : ?field=value
 *   LIKE              : ?field=%value%  OR  ?field[like]=value
 *   Greater than      : ?field=>value  OR  ?field[after]=value
 *   Less than         : ?field=<value  OR  ?field[before]=value
 *   Range             : ?field=min..max  OR  ?field[between]=min..max
 *   Relation field    : ?relation.field=value  OR  ?relation->field=value
 *   Pagination        : ?page=1&limit=20&sort=id&order=ASC
 *   Scope             : ?scope=mine|others|all  (names are configurable)
 */
class FilterManager implements FilterManagerInterface
{
    /**
     * @param SecurityUserResolverInterface $userResolver User resolver.
     *                                                     Uses NullSecurityUserResolver when SecurityBundle
     *                                                     is not installed — no exception is thrown.
     * @param int                           $maxLimit     Maximum allowed value for the "limit" query param.
     *                                                     Prevents abuse like ?limit=99999. Default: 100.
     * @param array<string, string>         $scopes       Scope name mappings (configurable via bundle config).
     *                                                     Keys: mine, others, all. Values: query string names.
     * @param string                        $scopeField   Entity field representing ownership. Default: 'user'.
     */
    public function __construct(
        private readonly SecurityUserResolverInterface $userResolver,
        private readonly int $maxLimit = 100,
        private readonly array $scopes = [
            'mine'   => 'mine',
            'others' => 'others',
            'all'    => 'all',
        ],
        private readonly string $scopeField = 'user',
    ) {
    }

    /**
     * Maps an HTTP request to a custom repository method.
     *
     * The target method will receive: (array $filters, array $pagination, ?object $user, string $scope)
     *
     * @throws \InvalidArgumentException if the method does not exist on the repository
     */
    public function mapRequestToRepository(
        Request $request,
        ObjectRepository $repository,
        string $method
    ): mixed {
        if (!method_exists($repository, $method)) {
            $shortName = (new \ReflectionClass($repository))->getShortName();
            throw new \InvalidArgumentException(
                sprintf("Method '%s' does not exist in repository '%s'.", $method, $shortName)
            );
        }

        $filters    = $this->extractFilters($request);
        $pagination = $this->extractPagination($request);
        $scope      = $request->query->get('scope', $this->scopes['all'] ?? 'all');
        $user       = $this->userResolver->getCurrentUser();

        return $repository->$method($filters, $pagination, $user, $scope, $this);
    }

    /**
     * Applies extracted filter array to a Doctrine QueryBuilder.
     *
     * Supports relation notation (relation.field) — auto-adds LEFT JOIN.
     * Supported operators: =, LIKE, BETWEEN, >, <, >=, <=
     *
     * @param array<array{field: string, operator: string, value: mixed}> $filters
     */
    public static function applyFiltersToQueryBuilder(
        QueryBuilder $qb,
        array $filters,
        string $alias = 'e'
    ): void {
        foreach ($filters as $filter) {
            $field = $filter['field'];
            $param = uniqid(str_replace(['.', '->'], '_', $field) . '_', false);
            $value = $filter['value'];

            // Handle relation notation: relation.field → add LEFT JOIN if missing
            if (str_contains($field, '.')) {
                [$relation, $relField] = explode('.', $field, 2);

                $existingJoins = array_map(
                    static fn($join) => $join->getAlias(),
                    $qb->getDQLPart('join')[$alias] ?? []
                );

                if (!in_array($relation, $existingJoins, true)) {
                    $qb->leftJoin("$alias.$relation", $relation);
                }

                $field = "$relation.$relField";
            } else {
                $field = "$alias.$field";
            }

            switch ($filter['operator']) {
                case 'LIKE':
                    $qb->andWhere($qb->expr()->like($field, ":$param"))
                        ->setParameter($param, "%$value%");
                    break;

                case 'BETWEEN':
                    [$min, $max] = $value;
                    $qb->andWhere("$field BETWEEN :min_$param AND :max_$param")
                        ->setParameter("min_$param", $min)
                        ->setParameter("max_$param", $max);
                    break;

                case '>':
                case '<':
                case '>=':
                case '<=':
                    $qb->andWhere("$field {$filter['operator']} :$param")
                        ->setParameter($param, $value);
                    break;

                default:
                    $qb->andWhere("$field = :$param")->setParameter($param, $value);
            }
        }
    }

    /**
     * Applies pagination and sorting to a Doctrine QueryBuilder.
     *
     * @param array{page: int, limit: int, sort: string, order: string} $pagination
     */
    public static function applyPaginationToQueryBuilder(
        QueryBuilder $qb,
        array $pagination,
        string $alias = 'e'
    ): void {
        $qb->orderBy("$alias.{$pagination['sort']}", $pagination['order'])
            ->setFirstResult(($pagination['page'] - 1) * $pagination['limit'])
            ->setMaxResults($pagination['limit']);
    }

    /**
     * Applies user scope filtering to a QueryBuilder using explicit configuration.
     *
     * When $user is null (no authentication), scope is silently ignored.
     *
     * @param string                $scope       Scope value from query string
     * @param object|null           $user        Currently authenticated user (or null)
     * @param string                $alias       QueryBuilder entity alias
     * @param string                $scopeField  Entity field representing ownership
     * @param array<string, string> $scopes      Scope name mappings (mine/others/all)
     */
    public static function applyScopeToQueryBuilder(
        QueryBuilder $qb,
        string $scope,
        ?object $user,
        string $alias = 'e',
        string $scopeField = 'user',
        array $scopes = ['mine' => 'mine', 'others' => 'others', 'all' => 'all'],
    ): void {
        // If no user is available (Security not installed or not authenticated), ignore scope silently
        if (!$user) {
            return;
        }

        $mineScope   = $scopes['mine']   ?? 'mine';
        $othersScope = $scopes['others'] ?? 'others';

        if ($scope === $mineScope) {
            $qb->andWhere("$alias.$scopeField = :currentUser")
                ->setParameter('currentUser', $user);
        } elseif ($scope === $othersScope) {
            $qb->andWhere("$alias.$scopeField != :currentUser")
                ->setParameter('currentUser', $user);
        }
        // 'all' or any unknown scope value → no filter applied
    }

    /**
     * Instance version of applyScopeToQueryBuilder that uses the bundle configuration.
     * Use this inside repositories when you have the FilterManager service injected.
     */
    public function applyScopeWithConfig(
        QueryBuilder $qb,
        string $scope,
        ?object $user,
        string $alias = 'e'
    ): void {
        self::applyScopeToQueryBuilder($qb, $scope, $user, $alias, $this->scopeField, $this->scopes);
    }

    // -------------------------------------------------------------------------
    // Private extraction methods (ported faithfully from the original source)
    // -------------------------------------------------------------------------

    /**
     * Extracts and normalizes all filter parameters from the request query string.
     * Reserved keys (page, limit, sort, order, scope) are excluded.
     *
     * @return array<array{field: string, operator: string, value: mixed}>
     */
    private function extractFilters(Request $request): array
    {
        $filters = [];

        foreach ($request->query->all() as $key => $value) {
            if (in_array($key, ['page', 'limit', 'sort', 'order', 'scope'], true)) {
                continue;
            }

            // Normalize relation separator: -> becomes .
            $key = str_replace('->', '.', $key);

            if (is_array($value)) {
                $filters = array_merge($filters, $this->extractComplexFilter($key, $value));
            } else {
                $filters[] = $this->extractSimpleFilter($key, (string) $value);
            }
        }

        return $filters;
    }

    /**
     * Extracts filters from array-style query params.
     * e.g. ?field[like]=value, ?field[between]=min..max, ?field[after]=2020-01-01
     *
     * @param array<string, mixed> $values
     * @return array<array{field: string, operator: string, value: mixed}>
     */
    private function extractComplexFilter(string $field, array $values): array
    {
        $filters = [];

        foreach ($values as $subKey => $subValue) {
            $operator   = $this->mapOperator((string) $subKey);
            $normalized = $this->normalizeValue($subValue);

            if (
                $operator === 'BETWEEN'
                && is_string($subValue)
                && preg_match('/^(.*)\.\.(.*)$/', $subValue, $m)
            ) {
                $normalized = [$this->normalizeValue($m[1]), $this->normalizeValue($m[2])];
            }

            $filters[] = [
                'field'    => $field,
                'operator' => $operator,
                'value'    => $normalized,
            ];
        }

        return $filters;
    }

    /**
     * Extracts a filter from a simple key=value query param.
     * Supports inline operator prefixes: %value%, >value, <value, min..max
     *
     * @return array{field: string, operator: string, value: mixed}
     */
    private function extractSimpleFilter(string $field, string $value): array
    {
        $operator = '=';

        if (preg_match('/^%.*%$/', $value)) {
            $operator = 'LIKE';
            $value    = trim($value, '%');
        } elseif (preg_match('/^>(.*)/', $value, $m)) {
            $operator = '>';
            $value    = trim($m[1]);
        } elseif (preg_match('/^<(.*)/', $value, $m)) {
            $operator = '<';
            $value    = trim($m[1]);
        } elseif (preg_match('/^(.*)\.\.(.*)$/', $value, $m)) {
            $operator = 'BETWEEN';
            $value    = [$m[1], $m[2]];
        }

        return [
            'field'    => $field,
            'operator' => $operator,
            'value'    => is_array($value)
                ? array_map(fn($v) => $this->normalizeValue($v), $value)
                : $this->normalizeValue($value),
        ];
    }

    /**
     * Maps a friendly operator name (from query string key) to its SQL operator.
     */
    private function mapOperator(string $subKey): string
    {
        return match (strtolower($subKey)) {
            'like'    => 'LIKE',
            'before'  => '<',
            'after'   => '>',
            'from'    => '>=',
            'to'      => '<=',
            'between' => 'BETWEEN',
            default   => '=',
        };
    }

    /**
     * Normalizes a scalar value to its proper PHP type.
     * Converts: booleans, integers, floats, ISO date strings.
     */
    private function normalizeValue(mixed $value): mixed
    {
        if (!is_string($value)) {
            return $value;
        }

        $trim = trim($value);

        if ($trim === 'true' || $trim === '1') {
            return true;
        }

        if ($trim === 'false' || $trim === '0') {
            return false;
        }

        if (is_numeric($trim)) {
            return ctype_digit($trim) ? (int) $trim : (float) $trim;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}(T.*)?$/', $trim)) {
            try {
                return new \DateTimeImmutable($trim);
            } catch (\Exception) {
                // Not a valid date — return as-is
            }
        }

        return $value;
    }

    /**
     * Extracts pagination parameters from the request.
     *
     * The limit is capped at $this->maxLimit to prevent resource exhaustion
     * from requests like ?limit=99999.
     *
     * @return array{page: int, limit: int, sort: string, order: string}
     */
    private function extractPagination(Request $request): array
    {
        $page  = max(1, (int) $request->query->get('page', 1));
        $limit = min(
            max(1, (int) $request->query->get('limit', 20)),
            $this->maxLimit  // Cap to prevent abuse
        );
        $sort  = $request->query->get('sort', 'id');
        $order = strtoupper($request->query->get('order', 'ASC'));

        return compact('page', 'limit', 'sort', 'order');
    }
}
