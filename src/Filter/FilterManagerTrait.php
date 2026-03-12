<?php

declare(strict_types=1);

namespace EricGansa\FilterManagerBundle\Filter;

/**
 * Trait for Doctrine repositories that integrate with FilterManager.
 *
 * Usage:
 *   class ArticleRepository extends ServiceEntityRepository
 *   {
 *       use FilterManagerTrait;
 *   }
 *
 * Then call: $repo->findByFilterManager($filters, $pagination, $user, $scope, $filterManager)
 */
trait FilterManagerTrait
{
    /**
     * Applies dynamic filters, pagination and user scope to a Doctrine query.
     *
     * @param array<array{field: string, operator: string, value: mixed}> $filters      Filters extracted from the request
     * @param array{page: int, limit: int, sort: string, order: string}   $pagination   Pagination parameters
     * @param object|null                                                  $user         Current authenticated user (or null)
     * @param string                                                       $scope        Scope value from query string
     * @param FilterManager|null                                           $filterManager FilterManager service for configured scopes.
     *                                                                                    If null, static defaults are used.
     *
     * @return array<object>
     */
    public function findByFilterManager(
        array $filters,
        array $pagination,
        ?object $user,
        string $scope,
        ?FilterManager $filterManager = null,
    ): array {
        $alias = 'e';
        $qb    = $this->createQueryBuilder($alias);

        FilterManager::applyFiltersToQueryBuilder($qb, $filters, $alias);
        FilterManager::applyPaginationToQueryBuilder($qb, $pagination, $alias);

        if ($filterManager !== null) {
            // Use configured scope field and scope names from bundle config
            $filterManager->applyScopeWithConfig($qb, $scope, $user, $alias);
        } else {
            // Fallback: static call with default configuration
            FilterManager::applyScopeToQueryBuilder($qb, $scope, $user, $alias);
        }

        return $qb->getQuery()->getResult();
    }
}
