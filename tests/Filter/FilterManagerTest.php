<?php

declare(strict_types=1);

namespace EricGansa\FilterManagerBundle\Tests\Filter;

use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ObjectRepository;
use EricGansa\FilterManagerBundle\Contract\SecurityUserResolverInterface;
use EricGansa\FilterManagerBundle\Filter\FilterManager;
use EricGansa\FilterManagerBundle\Security\NullSecurityUserResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\User\UserInterface;

#[CoversClass(FilterManager::class)]
class FilterManagerTest extends TestCase
{
    private FilterManager $filterManager;

    /** @var SecurityUserResolverInterface&MockObject */
    private SecurityUserResolverInterface $userResolver;

    protected function setUp(): void
    {
        $this->userResolver   = $this->createMock(SecurityUserResolverInterface::class);
        $this->filterManager  = new FilterManager($this->userResolver);
    }

    // =========================================================================
    // extractPagination (tested via mapRequestToRepository)
    // =========================================================================

    public function testExtractPaginationDefaults(): void
    {
        $request = new Request();
        $pagination = $this->invokePagination($request);

        $this->assertSame(['page' => 1, 'limit' => 20, 'sort' => 'id', 'order' => 'ASC'], $pagination);
    }

    public function testExtractPaginationCustomValues(): void
    {
        $request    = new Request(['page' => '3', 'limit' => '50', 'sort' => 'name', 'order' => 'desc']);
        $pagination = $this->invokePagination($request);

        $this->assertSame(['page' => 3, 'limit' => 50, 'sort' => 'name', 'order' => 'DESC'], $pagination);
    }

    public function testExtractPaginationPageMinIsOne(): void
    {
        $request    = new Request(['page' => '-5']);
        $pagination = $this->invokePagination($request);

        $this->assertSame(1, $pagination['page']);
    }

    public function testExtractPaginationLimitMinIsOne(): void
    {
        $request    = new Request(['limit' => '0']);
        $pagination = $this->invokePagination($request);

        $this->assertSame(1, $pagination['limit']);
    }

    /**
     * Improvement 5.2 — max_limit cap prevents abuse like ?limit=99999
     */
    public function testExtractPaginationLimitCappedAtMaxLimit(): void
    {
        $fm         = new FilterManager($this->userResolver, maxLimit: 100);
        $request    = new Request(['limit' => '99999']);
        $pagination = $this->invokePaginationOn($fm, $request);

        $this->assertSame(100, $pagination['limit']);
    }

    public function testExtractPaginationLimitRespectsCustomMaxLimit(): void
    {
        $fm         = new FilterManager($this->userResolver, maxLimit: 250);
        $request    = new Request(['limit' => '200']);
        $pagination = $this->invokePaginationOn($fm, $request);

        $this->assertSame(200, $pagination['limit']);
    }

    public function testExtractPaginationLimitExceedingCustomMaxLimit(): void
    {
        $fm         = new FilterManager($this->userResolver, maxLimit: 50);
        $request    = new Request(['limit' => '200']);
        $pagination = $this->invokePaginationOn($fm, $request);

        $this->assertSame(50, $pagination['limit']);
    }

    // =========================================================================
    // extractFilters — simple operators
    // =========================================================================

    public function testExtractFiltersSimpleEquality(): void
    {
        $request = new Request(['name' => 'Alice']);
        $filters = $this->invokeFilters($request);

        $this->assertCount(1, $filters);
        $this->assertSame('name', $filters[0]['field']);
        $this->assertSame('=', $filters[0]['operator']);
        $this->assertSame('Alice', $filters[0]['value']);
    }

    public function testExtractFiltersLikeInlinePercent(): void
    {
        $request = new Request(['name' => '%Alice%']);
        $filters = $this->invokeFilters($request);

        $this->assertSame('LIKE', $filters[0]['operator']);
        $this->assertSame('Alice', $filters[0]['value']);
    }

    public function testExtractFiltersGreaterThan(): void
    {
        $request = new Request(['age' => '>30']);
        $filters = $this->invokeFilters($request);

        $this->assertSame('>', $filters[0]['operator']);
        $this->assertSame(30, $filters[0]['value']);
    }

    public function testExtractFiltersLessThan(): void
    {
        $request = new Request(['age' => '<18']);
        $filters = $this->invokeFilters($request);

        $this->assertSame('<', $filters[0]['operator']);
        $this->assertSame(18, $filters[0]['value']);
    }

    public function testExtractFiltersBetweenInline(): void
    {
        $request = new Request(['age' => '18..65']);
        $filters = $this->invokeFilters($request);

        $this->assertSame('BETWEEN', $filters[0]['operator']);
        $this->assertSame([18, 65], $filters[0]['value']);
    }

    public function testExtractFiltersReservedKeysIgnored(): void
    {
        $request = new Request([
            'page'  => '2',
            'limit' => '10',
            'sort'  => 'name',
            'order' => 'desc',
            'scope' => 'mine',
            'name'  => 'Alice',
        ]);
        $filters = $this->invokeFilters($request);

        $this->assertCount(1, $filters);
        $this->assertSame('name', $filters[0]['field']);
    }

    public function testExtractFiltersArrowNotationNormalized(): void
    {
        $request = new Request(['user->name' => 'Alice']);
        $filters = $this->invokeFilters($request);

        $this->assertSame('user.name', $filters[0]['field']);
    }

    public function testExtractFiltersRelationDotNotation(): void
    {
        $request = new Request(['user.email' => 'alice@example.com']);
        $filters = $this->invokeFilters($request);

        $this->assertSame('user.email', $filters[0]['field']);
        $this->assertSame('=', $filters[0]['operator']);
        $this->assertSame('alice@example.com', $filters[0]['value']);
    }

    // =========================================================================
    // extractFilters — complex array operators
    // =========================================================================

    public function testExtractFiltersArrayLike(): void
    {
        $request = new Request(['name' => ['like' => 'Alice']]);
        $filters = $this->invokeFilters($request);

        $this->assertSame('LIKE', $filters[0]['operator']);
        $this->assertSame('Alice', $filters[0]['value']);
    }

    public function testExtractFiltersArrayBetween(): void
    {
        $request = new Request(['date' => ['between' => '1980-01-01..1990-12-31']]);
        $filters = $this->invokeFilters($request);

        $this->assertSame('BETWEEN', $filters[0]['operator']);
        $this->assertInstanceOf(\DateTimeImmutable::class, $filters[0]['value'][0]);
        $this->assertInstanceOf(\DateTimeImmutable::class, $filters[0]['value'][1]);
    }

    public function testExtractFiltersArrayAfter(): void
    {
        $request = new Request(['date' => ['after' => '2020-01-01']]);
        $filters = $this->invokeFilters($request);

        $this->assertSame('>', $filters[0]['operator']);
        $this->assertInstanceOf(\DateTimeImmutable::class, $filters[0]['value']);
    }

    public function testExtractFiltersArrayBefore(): void
    {
        $request = new Request(['date' => ['before' => '2020-01-01']]);
        $filters = $this->invokeFilters($request);

        $this->assertSame('<', $filters[0]['operator']);
        $this->assertInstanceOf(\DateTimeImmutable::class, $filters[0]['value']);
    }

    public function testExtractFiltersArrayFrom(): void
    {
        $request = new Request(['price' => ['from' => '10']]);
        $filters = $this->invokeFilters($request);

        $this->assertSame('>=', $filters[0]['operator']);
        $this->assertSame(10, $filters[0]['value']);
    }

    public function testExtractFiltersArrayTo(): void
    {
        $request = new Request(['price' => ['to' => '100']]);
        $filters = $this->invokeFilters($request);

        $this->assertSame('<=', $filters[0]['operator']);
        $this->assertSame(100, $filters[0]['value']);
    }

    public function testExtractFiltersArrayDefaultOperator(): void
    {
        $request = new Request(['status' => ['unknown_key' => 'active']]);
        $filters = $this->invokeFilters($request);

        $this->assertSame('=', $filters[0]['operator']);
        $this->assertSame('active', $filters[0]['value']);
    }

    // =========================================================================
    // normalizeValue
    // =========================================================================

    public function testNormalizeValueTrue(): void
    {
        $this->assertTrue($this->invokeNormalize('true'));
    }

    public function testNormalizeValueOne(): void
    {
        $this->assertTrue($this->invokeNormalize('1'));
    }

    public function testNormalizeValueFalse(): void
    {
        $this->assertFalse($this->invokeNormalize('false'));
    }

    public function testNormalizeValueZero(): void
    {
        $this->assertFalse($this->invokeNormalize('0'));
    }

    public function testNormalizeValueInteger(): void
    {
        $this->assertSame(42, $this->invokeNormalize('42'));
    }

    public function testNormalizeValueFloat(): void
    {
        $this->assertSame(3.14, $this->invokeNormalize('3.14'));
    }

    public function testNormalizeValueDate(): void
    {
        $result = $this->invokeNormalize('2024-06-15');
        $this->assertInstanceOf(\DateTimeImmutable::class, $result);
    }

    public function testNormalizeValueDatetime(): void
    {
        $result = $this->invokeNormalize('2024-06-15T10:30:00');
        $this->assertInstanceOf(\DateTimeImmutable::class, $result);
    }

    public function testNormalizeValueString(): void
    {
        $this->assertSame('hello', $this->invokeNormalize('hello'));
    }

    public function testNormalizeValueNonString(): void
    {
        $array  = ['a' => 1];
        $result = $this->invokeNormalize($array);
        $this->assertSame($array, $result);
    }

    public function testNormalizeValueInvalidDateReturnsString(): void
    {
        // Looks like a date format but is invalid — should return as string
        $result = $this->invokeNormalize('2024-99-99');
        $this->assertSame('2024-99-99', $result);
    }

    // =========================================================================
    // applyFiltersToQueryBuilder (static)
    // =========================================================================

    public function testApplyFiltersEquality(): void
    {
        $qb = $this->buildMockQb(['andWhere', 'setParameter', 'expr']);
        $qb->method('getDQLPart')->willReturn([]);

        $expr = $this->createMock(\Doctrine\ORM\Query\Expr::class);
        $qb->method('expr')->willReturn($expr);

        $qb->expects($this->once())->method('andWhere')->willReturnSelf();
        $qb->expects($this->once())->method('setParameter')->willReturnSelf();

        FilterManager::applyFiltersToQueryBuilder(
            $qb,
            [['field' => 'name', 'operator' => '=', 'value' => 'Alice']],
            'e'
        );
    }

    public function testApplyFiltersLike(): void
    {
        $qb   = $this->buildMockQb(['andWhere', 'setParameter', 'expr']);
        $expr = $this->createMock(\Doctrine\ORM\Query\Expr::class);
        $expr->method('like')->willReturn(new \Doctrine\ORM\Query\Expr\Comparison('e.name', 'LIKE', ':param'));
        $qb->method('expr')->willReturn($expr);
        $qb->method('getDQLPart')->willReturn([]);
        $qb->method('andWhere')->willReturnSelf();
        $qb->expects($this->once())->method('setParameter')->with($this->anything(), '%Alice%')->willReturnSelf();

        FilterManager::applyFiltersToQueryBuilder(
            $qb,
            [['field' => 'name', 'operator' => 'LIKE', 'value' => 'Alice']],
            'e'
        );
    }

    public function testApplyFiltersBetween(): void
    {
        $qb = $this->buildMockQb(['andWhere', 'setParameter']);
        $qb->method('getDQLPart')->willReturn([]);
        $qb->method('andWhere')->willReturnSelf();
        $qb->expects($this->exactly(2))->method('setParameter')->willReturnSelf();

        FilterManager::applyFiltersToQueryBuilder(
            $qb,
            [['field' => 'age', 'operator' => 'BETWEEN', 'value' => [18, 65]]],
            'e'
        );
    }

    public function testApplyFiltersGreaterThan(): void
    {
        $qb = $this->buildMockQb(['andWhere', 'setParameter']);
        $qb->method('getDQLPart')->willReturn([]);
        $qb->method('andWhere')->willReturnSelf();
        $qb->expects($this->once())->method('setParameter')->willReturnSelf();

        FilterManager::applyFiltersToQueryBuilder(
            $qb,
            [['field' => 'age', 'operator' => '>', 'value' => 18]],
            'e'
        );
    }

    public function testApplyFiltersLessThan(): void
    {
        $qb = $this->buildMockQb(['andWhere', 'setParameter']);
        $qb->method('getDQLPart')->willReturn([]);
        $qb->method('andWhere')->willReturnSelf();
        $qb->expects($this->once())->method('setParameter')->willReturnSelf();

        FilterManager::applyFiltersToQueryBuilder(
            $qb,
            [['field' => 'age', 'operator' => '<', 'value' => 65]],
            'e'
        );
    }

    public function testApplyFiltersGte(): void
    {
        $qb = $this->buildMockQb(['andWhere', 'setParameter']);
        $qb->method('getDQLPart')->willReturn([]);
        $qb->method('andWhere')->willReturnSelf();
        $qb->expects($this->once())->method('setParameter')->willReturnSelf();

        FilterManager::applyFiltersToQueryBuilder(
            $qb,
            [['field' => 'price', 'operator' => '>=', 'value' => 10]],
            'e'
        );
    }

    public function testApplyFiltersLte(): void
    {
        $qb = $this->buildMockQb(['andWhere', 'setParameter']);
        $qb->method('getDQLPart')->willReturn([]);
        $qb->method('andWhere')->willReturnSelf();
        $qb->expects($this->once())->method('setParameter')->willReturnSelf();

        FilterManager::applyFiltersToQueryBuilder(
            $qb,
            [['field' => 'price', 'operator' => '<=', 'value' => 100]],
            'e'
        );
    }

    public function testApplyFiltersRelationAddsJoin(): void
    {
        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('getDQLPart')->willReturn([]);
        $qb->expects($this->once())->method('leftJoin')->with('e.user', 'user')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();

        FilterManager::applyFiltersToQueryBuilder(
            $qb,
            [['field' => 'user.name', 'operator' => '=', 'value' => 'Alice']],
            'e'
        );
    }

    public function testApplyFiltersRelationDoesNotDuplicateJoin(): void
    {
        $joinAlias = $this->createMock(\Doctrine\ORM\Query\Expr\Join::class);
        $joinAlias->method('getAlias')->willReturn('user');

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('getDQLPart')->willReturn(['e' => [$joinAlias]]);
        $qb->expects($this->never())->method('leftJoin');
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();

        FilterManager::applyFiltersToQueryBuilder(
            $qb,
            [['field' => 'user.name', 'operator' => '=', 'value' => 'Alice']],
            'e'
        );
    }

    // =========================================================================
    // applyPaginationToQueryBuilder (static)
    // =========================================================================

    public function testApplyPaginationToQueryBuilder(): void
    {
        $qb = $this->createMock(QueryBuilder::class);
        $qb->expects($this->once())->method('orderBy')->with('e.name', 'ASC')->willReturnSelf();
        $qb->expects($this->once())->method('setFirstResult')->with(20)->willReturnSelf();
        $qb->expects($this->once())->method('setMaxResults')->with(10)->willReturnSelf();

        FilterManager::applyPaginationToQueryBuilder(
            $qb,
            ['page' => 3, 'limit' => 10, 'sort' => 'name', 'order' => 'ASC'],
            'e'
        );
    }

    public function testApplyPaginationFirstPageOffset(): void
    {
        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('orderBy')->willReturnSelf();
        $qb->expects($this->once())->method('setFirstResult')->with(0)->willReturnSelf();
        $qb->method('setMaxResults')->willReturnSelf();

        FilterManager::applyPaginationToQueryBuilder(
            $qb,
            ['page' => 1, 'limit' => 20, 'sort' => 'id', 'order' => 'ASC'],
            'e'
        );
    }

    // =========================================================================
    // applyScopeToQueryBuilder (static)
    // =========================================================================

    public function testApplyScopeMine(): void
    {
        $user = $this->createMock(UserInterface::class);
        $qb   = $this->createMock(QueryBuilder::class);
        $qb->expects($this->once())->method('andWhere')->with('e.user = :currentUser')->willReturnSelf();
        $qb->expects($this->once())->method('setParameter')->with('currentUser', $user)->willReturnSelf();

        FilterManager::applyScopeToQueryBuilder($qb, 'mine', $user);
    }

    public function testApplyScopeOthers(): void
    {
        $user = $this->createMock(UserInterface::class);
        $qb   = $this->createMock(QueryBuilder::class);
        $qb->expects($this->once())->method('andWhere')->with('e.user != :currentUser')->willReturnSelf();
        $qb->expects($this->once())->method('setParameter')->willReturnSelf();

        FilterManager::applyScopeToQueryBuilder($qb, 'others', $user);
    }

    public function testApplyScopeAllNoFilter(): void
    {
        $user = $this->createMock(UserInterface::class);
        $qb   = $this->createMock(QueryBuilder::class);
        $qb->expects($this->never())->method('andWhere');

        FilterManager::applyScopeToQueryBuilder($qb, 'all', $user);
    }

    public function testApplyScopeNullUserSkips(): void
    {
        $qb = $this->createMock(QueryBuilder::class);
        $qb->expects($this->never())->method('andWhere');

        FilterManager::applyScopeToQueryBuilder($qb, 'mine', null);
    }

    /**
     * Improvement 5.3 — custom scope names via configuration
     */
    public function testApplyScopeCustomScopeNames(): void
    {
        $user   = $this->createMock(UserInterface::class);
        $qb     = $this->createMock(QueryBuilder::class);
        $scopes = ['mine' => 'personal', 'others' => 'community', 'all' => 'everything'];

        $qb->expects($this->once())->method('andWhere')->with('e.user = :currentUser')->willReturnSelf();
        $qb->expects($this->once())->method('setParameter')->willReturnSelf();

        FilterManager::applyScopeToQueryBuilder($qb, 'personal', $user, 'e', 'user', $scopes);
    }

    public function testApplyScopeCustomScopeField(): void
    {
        $user = $this->createMock(UserInterface::class);
        $qb   = $this->createMock(QueryBuilder::class);

        $qb->expects($this->once())->method('andWhere')->with('e.owner = :currentUser')->willReturnSelf();
        $qb->expects($this->once())->method('setParameter')->willReturnSelf();

        FilterManager::applyScopeToQueryBuilder($qb, 'mine', $user, 'e', 'owner');
    }

    // =========================================================================
    // applyScopeWithConfig (instance method)
    // =========================================================================

    public function testApplyScopeWithConfig(): void
    {
        $user   = $this->createMock(UserInterface::class);
        $qb     = $this->createMock(QueryBuilder::class);
        $scopes = ['mine' => 'moi', 'others' => 'autres', 'all' => 'tout'];
        $fm     = new FilterManager($this->userResolver, 100, $scopes, 'auteur');

        $qb->expects($this->once())->method('andWhere')->with('e.auteur = :currentUser')->willReturnSelf();
        $qb->expects($this->once())->method('setParameter')->willReturnSelf();

        $fm->applyScopeWithConfig($qb, 'moi', $user);
    }

    // =========================================================================
    // mapRequestToRepository
    // =========================================================================

    public function testMapRequestToRepositoryCallsMethod(): void
    {
        $user = $this->createMock(UserInterface::class);
        $this->userResolver->method('getCurrentUser')->willReturn($user);

        $repository = $this->createMock(ObjectRepository::class);
        $request    = new Request(['scope' => 'mine', 'page' => '1']);

        // Add the custom method dynamically via an anonymous class
        $repository = new class ($user) implements ObjectRepository {
            public function __construct(private readonly object $expectedUser)
            {
            }

            public function findByFilterManager(
                array $filters,
                array $pagination,
                ?object $user,
                string $scope,
                ?FilterManager $filterManager = null
            ): array {
                return ['result'];
            }

            public function find(mixed $id): ?object { return null; }
            public function findAll(): array { return []; }
            public function findBy(array $criteria, ?array $orderBy = null, $limit = null, $offset = null): array { return []; }
            public function findOneBy(array $criteria): ?object { return null; }
            public function getClassName(): string { return 'Entity'; }
        };

        $this->userResolver->method('getCurrentUser')->willReturn($user);
        $fm     = new FilterManager($this->userResolver);
        $result = $fm->mapRequestToRepository($request, $repository, 'findByFilterManager');

        $this->assertSame(['result'], $result);
    }

    public function testMapRequestToRepositoryThrowsOnMissingMethod(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches("/Method 'nonExistent' does not exist/");

        $repository = $this->createMock(ObjectRepository::class);
        $request    = new Request();

        $this->filterManager->mapRequestToRepository($request, $repository, 'nonExistent');
    }

    public function testMapRequestToRepositoryUsesDefaultScope(): void
    {
        $called  = false;
        $capturedScope = null;

        $repository = new class ($called, $capturedScope) implements ObjectRepository {
            public function __construct(
                public bool &$called,
                public ?string &$capturedScope
            ) {
            }

            public function findByFilterManager(array $f, array $p, ?object $u, string $scope, ?FilterManager $fm = null): array
            {
                $this->called        = true;
                $this->capturedScope = $scope;
                return [];
            }

            public function find(mixed $id): ?object { return null; }
            public function findAll(): array { return []; }
            public function findBy(array $c, ?array $o = null, $l = null, $off = null): array { return []; }
            public function findOneBy(array $c): ?object { return null; }
            public function getClassName(): string { return 'Entity'; }
        };

        $this->userResolver->method('getCurrentUser')->willReturn(null);
        $this->filterManager->mapRequestToRepository(new Request(), $repository, 'findByFilterManager');

        $this->assertTrue($called);
        $this->assertSame('all', $capturedScope);
    }

    public function testMapRequestToRepositoryPassesFilterManagerInstanceToMethod(): void
    {
        $capturedFm = false;

        $repository = new class ($capturedFm) implements ObjectRepository {
            public function __construct(public mixed &$capturedFm) {}

            public function findByFilterManager(array $f, array $p, ?object $u, string $scope, ?FilterManager $fm = null): array
            {
                $this->capturedFm = $fm;
                return [];
            }

            public function find(mixed $id): ?object { return null; }
            public function findAll(): array { return []; }
            public function findBy(array $c, ?array $o = null, $l = null, $off = null): array { return []; }
            public function findOneBy(array $c): ?object { return null; }
            public function getClassName(): string { return 'Entity'; }
        };

        $fm = new FilterManager($this->userResolver, 100, ['mine' => 'moi', 'others' => 'autres', 'all' => 'tout'], 'auteur');
        $this->userResolver->method('getCurrentUser')->willReturn(null);
        $fm->mapRequestToRepository(new Request(), $repository, 'findByFilterManager');

        $this->assertSame($fm, $capturedFm);
    }

    // =========================================================================
    // Null security resolver integration (Improvement 5.1)
    // =========================================================================

    public function testWorksWithNullSecurityResolver(): void
    {
        $fm = new FilterManager(new NullSecurityUserResolver());

        $this->assertInstanceOf(FilterManager::class, $fm);
        // NullSecurityUserResolver returns null — scope filtering is silently skipped
        $qb = $this->createMock(QueryBuilder::class);
        $qb->expects($this->never())->method('andWhere');

        FilterManager::applyScopeToQueryBuilder($qb, 'mine', null);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /** @return array{page: int, limit: int, sort: string, order: string} */
    private function invokePagination(Request $request): array
    {
        return $this->invokePaginationOn($this->filterManager, $request);
    }

    /** @return array{page: int, limit: int, sort: string, order: string} */
    private function invokePaginationOn(FilterManager $fm, Request $request): array
    {
        $method = new \ReflectionMethod($fm, 'extractPagination');
        return $method->invoke($fm, $request);
    }

    /** @return array<array{field: string, operator: string, value: mixed}> */
    private function invokeFilters(Request $request): array
    {
        $method = new \ReflectionMethod($this->filterManager, 'extractFilters');
        return $method->invoke($this->filterManager, $request);
    }

    private function invokeNormalize(mixed $value): mixed
    {
        $method = new \ReflectionMethod($this->filterManager, 'normalizeValue');
        return $method->invoke($this->filterManager, $value);
    }

    /**
     * @param string[] $methods
     * @return QueryBuilder&MockObject
     */
    private function buildMockQb(array $methods): QueryBuilder&MockObject
    {
        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('getDQLPart')->willReturn([]);
        foreach ($methods as $method) {
            if (!in_array($method, ['andWhere', 'setParameter', 'expr', 'leftJoin'], true)) {
                $qb->method($method)->willReturnSelf();
            }
        }
        return $qb;
    }
}
