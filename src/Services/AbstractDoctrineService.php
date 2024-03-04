<?php

namespace Monderka\DoctrineTools\Services;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Exception\MissingColumnException;
use Doctrine\ORM\UnitOfWork;
use Monderka\DoctrineTools\Exceptions\EntityNotFoundException;
use Monderka\DoctrineTools\Interfaces\DoctrineEntityInterface;
use Monderka\DoctrineTools\Interfaces\RenewableDoctrineEntityInterface;
use Nette\Utils\Strings;
use Nettrine\ORM\EntityManagerDecorator;

/** @template T of DoctrineEntityInterface|RenewableDoctrineEntityInterface */
abstract class AbstractDoctrineService
{
    /** @var class-string */
    public static string $entityName = DoctrineEntityInterface::class;
    public static string $entityAlias = self::class;
    public static string $idColumn = "id";
    public static string $deletedColumn = "deleted";

    public const
        COUNT_FUNCTION = 'COUNT',
        SUM_FUNCTION = 'SUM',
        AVG_FUNCTION = 'AVG',
        MIN_FUNCTION = 'MIN',
        MAX_FUNCTION = 'MAX';

    public function __construct(
        protected readonly EntityManagerDecorator $em
    ) {
    }

    protected function isEntityRenewable(): bool
    {
        return in_array(
            RenewableDoctrineEntityInterface::class,
            class_implements(static::$entityName)
        );
    }

    public function getEm(): EntityManagerDecorator
    {
        return $this->em;
    }

    /** @return EntityRepository<T> */
    public function getRepository(): EntityRepository
    {
        /** @var EntityRepository<T> $repository */
        $repository =  $this->em->getRepository(static::$entityName);
        return $repository;
    }

    public function save(DoctrineEntityInterface $entity): void
    {
        if ($this->em->getUnitOfWork()->getEntityState($entity) === UnitOfWork::STATE_NEW) {
            $this->em->persist($entity);
        }
        $this->em->flush($entity);
    }

    public function refresh(DoctrineEntityInterface $entity): void
    {
        $this->em->refresh($entity);
    }

    public function getQueryBuilder(): QueryBuilder
    {
        return $this->em->createQueryBuilder()
            ->select(static::$entityAlias)
            ->from(static::$entityName, static::$entityAlias);
    }

    protected function getAggregateFunctionQueryBuilder(
        string $function,
        string $column,
        ?string $alias = null,
        ?string $entityAlias = null
    ): QueryBuilder {
        $eAlias = $entityAlias === null ? static::$entityAlias : $entityAlias;
        $field = empty($alias) ?
            $function . '(' . $eAlias . "." . $column . ')' :
            $function . '(' . $eAlias . "." . $column . ')' . ' AS ' . $alias;

        return $this->em->createQueryBuilder()
            ->select($field)
            ->from(static::$entityName, static::$entityAlias);
    }

    public function getCountQueryBuilder(
        string $column,
        ?string $alias = null,
        ?string $entityAlias = null
    ): QueryBuilder {
        return $this->getAggregateFunctionQueryBuilder(self::COUNT_FUNCTION, $column, $alias, $entityAlias);
    }

    public function getSumQueryBuilder(
        string $column,
        ?string $alias = null,
        ?string $entityAlias = null
    ): QueryBuilder {
        return $this->getAggregateFunctionQueryBuilder(self::SUM_FUNCTION, $column, $alias, $entityAlias);
    }

    public function getAvgQueryBuilder(
        string $column,
        ?string $alias = null,
        ?string $entityAlias = null
    ): QueryBuilder {
        return $this->getAggregateFunctionQueryBuilder(self::AVG_FUNCTION, $column, $alias, $entityAlias);
    }

    public function getMinQueryBuilder(
        string $column,
        ?string $alias = null,
        ?string $entityAlias = null
    ): QueryBuilder {
        return $this->getAggregateFunctionQueryBuilder(self::MIN_FUNCTION, $column, $alias, $entityAlias);
    }

    public function getMaxQueryBuilder(
        string $column,
        ?string $alias = null,
        ?string $entityAlias = null
    ): QueryBuilder {
        return $this->getAggregateFunctionQueryBuilder(self::MAX_FUNCTION, $column, $alias, $entityAlias);
    }

    protected function getDoctrineEntity(string|int $id): ?DoctrineEntityInterface
    {
        /** @var DoctrineEntityInterface|null $entity */
        $entity = $this->em
            ->getRepository(static::$entityName)
            ->find($id);
        return $entity;
    }

    protected function getRenewableDoctrineEntity(string|int $id): ?RenewableDoctrineEntityInterface
    {
        $qb = $this->getQueryBuilder();
        $qb->where(static::$entityAlias . "." . static::$idColumn . "=:id")
            ->setParameter("id", $id)
            ->andWhere($qb->expr()->isNull(static::$entityAlias . "." . static::$deletedColumn))
            ->setMaxResults(1);
        /** @var RenewableDoctrineEntityInterface[] $result */
        $result = $qb->getQuery()->getResult();
        if (count($result) === 0) {
            return null;
        } else {
            return $result[0];
        }
    }

    /**
     * @param string|int $id
     * @return T
     * @throws EntityNotFoundException
     */
    public function get(string|int $id): DoctrineEntityInterface|RenewableDoctrineEntityInterface
    {
        if ($this->isEntityRenewable()) {
            /** @var T|null $entity */
            $entity = $this->getRenewableDoctrineEntity($id);
        } else {
            /** @var T|null $entity */
            $entity = $this->getDoctrineEntity($id);
        }
        if (empty($entity)) {
            throw new EntityNotFoundException(
                "Entity " . static::$entityAlias . " with id " . $id . " was not found"
            );
        }
        return $entity;
    }

    /**
     * @param string|int $id
     * @return T|null
     */
    public function getOrNull(string|int $id): DoctrineEntityInterface|RenewableDoctrineEntityInterface|null
    {
        if ($this->isEntityRenewable()) {
            $entity = $this->getRenewableDoctrineEntity($id);
        } else {
            $entity = $this->getDoctrineEntity($id);
        }
        /** @var T|null $entity */
        return $entity;
    }

    /**
     * @param string|int $id
     * @return void
     * @throws EntityNotFoundException
     */
    public function delete(string|int $id): void
    {
        $entity = $this->get($id);
        if ($entity instanceof RenewableDoctrineEntityInterface) {
            $entity->setDeleted();
        } else {
            $this->em->remove($entity);
        }
        $this->em->flush($entity);
    }

    /**
     * @param string|int $id
     * @return RenewableDoctrineEntityInterface
     * @throws EntityNotFoundException
     */
    protected function getDeletedEntity(string|int $id): RenewableDoctrineEntityInterface
    {
        if ($this->isEntityRenewable()) {
            $qb = $this->getQueryBuilder();
            $qb->where(static::$entityAlias . "." . static::$idColumn . "=:id")
                ->setParameter("id", $id)
                ->andWhere($qb->expr()->isNotNull(static::$entityAlias . "." . static::$deletedColumn))
                ->setMaxResults(1);
            /** @var RenewableDoctrineEntityInterface[] $result */
            $result = $qb->getQuery()->getResult();
            if (count($result) === 0) {
                throw new EntityNotFoundException();
            } else {
                return $result[0];
            }
        } else {
            throw new EntityNotFoundException();
        }
    }

    /**
     * @param string|int $id
     * @return void
     * @throws EntityNotFoundException
     */
    public function undelete(string|int $id): void
    {
        $entity = $this->getDeletedEntity($id);
        $entity->renew();
        $this->em->flush($entity);
    }

    /**
     * @param string|int $id
     * @return void
     * @throws EntityNotFoundException
     */
    public function purge(string|int $id): void
    {
        $entity = $this->getDeletedEntity($id);
        $this->em->remove($entity);
        $this->em->flush($entity);
    }

    /**
     * @param string|int $id
     * @param string $key
     * @param string|int|float|bool|null $value
     * @return void
     * @throws EntityNotFoundException
     * @throws MissingColumnException
     */
    public function patch(
        string|int $id,
        string $key,
        string|int|float|bool|null $value
    ): void {
        $entity = $this->get($id);

        $setter = 'set' . Strings::firstUpper($key);
        if (!method_exists($entity, $setter) || $key === 'id') {
            throw new MissingColumnException();
        }
        $entity->$setter($value);
        $this->save($entity);
    }
}
