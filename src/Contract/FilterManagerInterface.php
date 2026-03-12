<?php

declare(strict_types=1);

namespace EricGansa\FilterManagerBundle\Contract;

use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ObjectRepository;
use Symfony\Component\HttpFoundation\Request;

/**
 * Contract for the FilterManager service.
 */
interface FilterManagerInterface
{
    /**
     * Maps an HTTP request to a custom repository method.
     *
     * @throws \InvalidArgumentException if the method does not exist on the repository
     */
    public function mapRequestToRepository(
        Request $request,
        ObjectRepository $repository,
        string $method
    ): mixed;

    /**
     * Applies extracted filters to a Doctrine QueryBuilder.
     *
     * @param array<array{field: string, operator: string, value: mixed}> $filters
     */
    public static function applyFiltersToQueryBuilder(
        QueryBuilder $qb,
        array $filters,
        string $alias = 'e'
    ): void;

    /**
     * Applies pagination and sorting to a Doctrine QueryBuilder.
     *
     * @param array{page: int, limit: int, sort: string, order: string} $pagination
     */
    public static function applyPaginationToQueryBuilder(
        QueryBuilder $qb,
        array $pagination,
        string $alias = 'e'
    ): void;
}
