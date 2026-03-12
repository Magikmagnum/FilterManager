<?php

declare(strict_types=1);

namespace EricGansa\FilterManagerBundle\Tests\Filter;

use Doctrine\ORM\Query;
use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\QueryBuilder;
use EricGansa\FilterManagerBundle\Contract\SecurityUserResolverInterface;
use EricGansa\FilterManagerBundle\Filter\FilterManager;
use EricGansa\FilterManagerBundle\Filter\FilterManagerTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(FilterManagerTrait::class)]
class FilterManagerTraitTest extends TestCase
{
    /** Concrete class using the trait for testing */
    private object $repo;

    /** @var QueryBuilder&MockObject */
    private QueryBuilder $qb;

    /** @var Query&MockObject */
    private Query $query;

    protected function setUp(): void
    {
        $this->query = $this->createMock(Query::class);
        $this->query->method('getResult')->willReturn([]);

        $expr = $this->createMock(Expr::class);
        $expr->method('eq')->willReturn(new Expr\Comparison('e.id', '=', ':param'));
        $expr->method('gt')->willReturn(new Expr\Comparison('e.id', '>', ':param'));
        $expr->method('lt')->willReturn(new Expr\Comparison('e.id', '<', ':param'));
        $expr->method('like')->willReturn(new Expr\Comparison('e.name', 'LIKE', ':param'));
        $expr->method('between')->willReturn('e.age BETWEEN :min AND :max');
        $expr->method('andX')->willReturn(new Expr\Andx());
        $expr->method('isNull')->willReturn('e.field IS NULL');
        $expr->method('neq')->willReturn(new Expr\Comparison('e.field', '<>', ':param'));

        $this->qb = $this->createMock(QueryBuilder::class);
        $this->qb->method('expr')->willReturn($expr);
        $this->qb->method('andWhere')->willReturnSelf();
        $this->qb->method('setParameter')->willReturnSelf();
        $this->qb->method('setFirstResult')->willReturnSelf();
        $this->qb->method('setMaxResults')->willReturnSelf();
        $this->qb->method('orderBy')->willReturnSelf();
        $this->qb->method('leftJoin')->willReturnSelf();
        $this->qb->method('getDQLPart')->willReturn([]);
        $this->qb->method('getQuery')->willReturn($this->query);

        $qb = $this->qb;

        $this->repo = new class ($qb) {
            use FilterManagerTrait;

            public function __construct(private readonly QueryBuilder $mockQb) {}

            public function createQueryBuilder(string $alias): QueryBuilder
            {
                return $this->mockQb;
            }
        };
    }

    public function testFindByFilterManagerReturnsArray(): void
    {
        $result = $this->repo->findByFilterManager([], ['page' => 1, 'limit' => 20, 'sort' => 'id', 'order' => 'ASC'], null, 'all');

        $this->assertIsArray($result);
    }

    public function testFindByFilterManagerWithFilters(): void
    {
        $filters = [['field' => 'name', 'operator' => '=', 'value' => 'Alice']];

        $this->qb->expects($this->atLeastOnce())->method('andWhere')->willReturnSelf();

        $result = $this->repo->findByFilterManager($filters, ['page' => 1, 'limit' => 20, 'sort' => 'id', 'order' => 'ASC'], null, 'all');

        $this->assertIsArray($result);
    }

    public function testFindByFilterManagerWithPagination(): void
    {
        $this->qb->expects($this->once())->method('setMaxResults')->with(10)->willReturnSelf();
        $this->qb->expects($this->once())->method('setFirstResult')->with(0)->willReturnSelf();

        $this->repo->findByFilterManager([], ['page' => 1, 'limit' => 10, 'sort' => 'id', 'order' => 'ASC'], null, 'all');
    }

    public function testFindByFilterManagerWithNullFilterManagerFallsBackToStatic(): void
    {
        $user = $this->createMock(\Symfony\Component\Security\Core\User\UserInterface::class);

        // no filterManager passed — static applyScopeToQueryBuilder is used (no andWhere for scope 'all')
        $this->qb->expects($this->never())->method('andWhere');

        $this->repo->findByFilterManager([], ['page' => 1, 'limit' => 20, 'sort' => 'id', 'order' => 'ASC'], $user, 'all');
    }

    public function testFindByFilterManagerWithFilterManagerService(): void
    {
        $filterManager = $this->createMock(FilterManager::class);
        $user          = $this->createMock(\Symfony\Component\Security\Core\User\UserInterface::class);

        $filterManager->expects($this->once())
            ->method('applyScopeWithConfig')
            ->with($this->qb, 'all', $user, 'e');

        $this->repo->findByFilterManager([], ['page' => 1, 'limit' => 20, 'sort' => 'id', 'order' => 'ASC'], $user, 'all', $filterManager);
    }

    public function testFindByFilterManagerWithMineScope(): void
    {
        $filterManager = $this->createMock(FilterManager::class);
        $user          = $this->createMock(\Symfony\Component\Security\Core\User\UserInterface::class);

        $filterManager->expects($this->once())
            ->method('applyScopeWithConfig')
            ->with($this->qb, 'mine', $user, 'e');

        $this->repo->findByFilterManager([], ['page' => 1, 'limit' => 20, 'sort' => 'id', 'order' => 'ASC'], $user, 'mine', $filterManager);
    }

    public function testFindByFilterManagerWithOthersScope(): void
    {
        $filterManager = $this->createMock(FilterManager::class);
        $user          = $this->createMock(\Symfony\Component\Security\Core\User\UserInterface::class);

        $filterManager->expects($this->once())
            ->method('applyScopeWithConfig')
            ->with($this->qb, 'others', $user, 'e');

        $this->repo->findByFilterManager([], ['page' => 1, 'limit' => 20, 'sort' => 'id', 'order' => 'ASC'], $user, 'others', $filterManager);
    }
}
