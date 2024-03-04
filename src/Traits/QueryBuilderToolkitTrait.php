<?php

namespace Monderka\DoctrineTools\Traits;

use Doctrine\ORM\QueryBuilder;
use Monderka\DoctrineTools\Interfaces\DoctrineEntityInterface;

trait QueryBuilderToolkitTrait
{
    protected function addNonDeletedCondition(
        QueryBuilder $qb,
        string $entityAlias,
        string $deletedColumn = "deleted"
    ): void {
        $qb->where($entityAlias . "." . $deletedColumn . " IS NULL");
    }

    protected function addEmptyCondition(
        QueryBuilder $qb,
        string $entityAlias,
        string $idColumn = "id"
    ): void {
        $qb->where($entityAlias . "." . $idColumn . " IS NOT NULL");
    }

    /**
     * @param QueryBuilder $qb
     * @param array<string, "ASC"|"DESC"> $sorts
     * @param string $entityAlias
     * @return void
     */
    protected function addSorting(QueryBuilder $qb, array $sorts, string $entityAlias): void
    {
        foreach ($sorts as $column => $sort) {
            $qb->addOrderBy($entityAlias . "." . $column, $sort);
        }
    }

    /**
     * @param QueryBuilder $qb
     * @param array{ "offset"?: int, "limit"?: int } $pagination
     * @return void
     */
    protected function addPagination(QueryBuilder $qb, array $pagination): void
    {
        if (
            isset($pagination["offset"]) &&
            is_int($pagination["offset"]) &&
            isset($pagination["limit"]) &&
            is_int($pagination["limit"])
        ) {
            $qb->setFirstResult($pagination["offset"]);
            $qb->setMaxResults($pagination["limit"]);
        }
    }

    protected function trimLikeValue(
        string|int|float|bool $filter,
        string $left = "%",
        string $right = "%"
    ): string {
        return $left . addcslashes((string) $filter, '%_') . $right;
    }

    /** @return array<int, array<string, mixed>> */
    protected function getItemsResult(
        QueryBuilder $qb,
        callable $exportCallback,
        bool $cacheable = false
    ): array {
        $res = [];
        $query = $qb->getQuery();
        $query->setCacheable($cacheable);
        /** @var DoctrineEntityInterface[] $result */
        $result = $query->getResult();
        foreach ($result as $entity) {
            $item = $exportCallback($entity);
            if (!empty($item)) {
                $res[] = $item;
            }
        }
        return $res;
    }


    /** @noinspection PhpUnhandledExceptionInspection */
    protected function getCountResult(QueryBuilder $qb): int
    {
        $count = $qb->getQuery()->getSingleScalarResult();
        assert(is_scalar($count));
        return (int) $count;
    }
}
