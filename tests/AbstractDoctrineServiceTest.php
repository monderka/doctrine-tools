<?php

namespace Monderka\DoctrineTools\Test;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Exception\MissingMappingDriverImplementation;
use Doctrine\ORM\ORMSetup;
use Monderka\DoctrineTools\Exceptions\EntityNotFoundException;
use Monderka\DoctrineTools\Services\AbstractDoctrineService;
use Nette\Utils\Strings;
use Nettrine\ORM\EntityManagerDecorator;
use PHPUnit\Framework\TestCase;

final class AbstractDoctrineServiceTest extends TestCase
{
    public const ENTITY_ALIAS = "test";

    private AbstractDoctrineService $service;
    private AbstractDoctrineService $renewableService;
    private ?int $id = null;
    private EntityManagerDecorator $em;

    /**
     * @throws MissingMappingDriverImplementation
     * @throws Exception
     */
    protected function setUp(): void
    {
        parent::setUp();
        $config = ORMSetup::createAttributeMetadataConfiguration(
            paths: array(__DIR__ . "/src"),
            isDevMode: true,
        );
        $connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'path' => __DIR__ . '/../db.sqlite',
        ], $config);
        $this->em = new EntityManagerDecorator(new EntityManager($connection, $config));
        $connection->executeQuery(TestEntity::DROP_SQL);
        $connection->executeQuery(TestEntity::CREATE_SQL);
        $this->service = new class($this->em) extends AbstractDoctrineService {
            public static string $entityName = TestEntity::class;
            public static string $entityAlias = AbstractDoctrineServiceTest::ENTITY_ALIAS;
            public static string $idColumn = "id";
            public static string $deletedColumn = "deletor";
        };
        $connection->executeQuery(RenewableTestEntity::DROP_SQL);
        $connection->executeQuery(RenewableTestEntity::CREATE_SQL);
        $this->renewableService = new class($this->em) extends AbstractDoctrineService {
            public static string $entityName = RenewableTestEntity::class;
            public static string $entityAlias = AbstractDoctrineServiceTest::ENTITY_ALIAS;
            public static string $idColumn = "id";
            public static string $deletedColumn = "deletor";
        };
    }

    private function entityProvider(): array
    {
        return [
            "name" => "test"
        ];
    }

    private function secondaryEntityProvider(): array
    {
        return [
            "name" => "test2"
        ];
    }

    private function createEntity($array, string $entity): void
    {
        $entity = new $entity;
        $entity->setName($array["name"]);
        $this->em->persist($entity);
        $this->em->flush();
        $this->id = $entity->getId();
    }

    private function createSecondaryEntity($array, string $entity): void
    {
        $entity = new $entity;
        $entity->setName($array["name"]);
        $this->em->persist($entity);
        $this->em->flush();
    }

    public function testGetEm(): void
    {
        $this->assertInstanceOf(EntityManagerDecorator::class, $this->service->getEm());
    }

    public function testSave(): void
    {
        $entity = (new TestEntity())
            ->setName($this->entityProvider()["name"]);

        $this->service->save($entity);

        $this->em->refresh($entity);
        $this->id = $entity->getId();

        $entity = $this->em->find(TestEntity::class, $this->id);
        $this->assertEquals($this->entityProvider()["name"], $entity->getName());
    }

    public function testRefresh(): void
    {
        $entity = (new TestEntity())
            ->setName($this->entityProvider()["name"]);
        $this->service->save($entity);
        $this->id = $entity->getId();

        $entity = $this->em->find(TestEntity::class, $this->id);
        $entity->setName("testXXX");
        $this->service->refresh($entity);

        $this->assertEquals($this->entityProvider()["name"], $entity->getName());
    }

    public function testGet(): void
    {
        $this->createEntity($this->entityProvider(), TestEntity::class);

        $entity = $this->service->get($this->id);

        $this->assertEquals($this->id, $entity->getId());
        $this->assertEquals($this->entityProvider()["name"], $entity->getName());

        $this->expectException(EntityNotFoundException::class);
        $this->service->get($this->id + 999);
    }

    public function testGetOrNull(): void
    {
        $this->createEntity($this->entityProvider(), TestEntity::class);

        $entity = $this->service->getOrNull($this->id);

        $this->assertEquals($this->id, $entity->getId());
        $this->assertEquals($this->entityProvider()["name"], $entity->getName());

        $entity = $this->service->getOrNull($this->id + 999);
        $this->assertNull($entity);
    }

    public function testGetRenewableEntity(): void
    {
        $this->createEntity($this->entityProvider(), RenewableTestEntity::class);

        $entity = $this->renewableService->get($this->id);

        $this->assertEquals($this->id, $entity->getId());
        $this->assertEquals($this->entityProvider()["name"], $entity->getName());

        $entity->setDeletor("Test");
        $this->em->flush($entity);
        $this->expectException(EntityNotFoundException::class);
        $this->renewableService->get($this->id);
    }

    public function testGetRenewableEntityOrNull(): void
    {
        $this->createEntity($this->entityProvider(), RenewableTestEntity::class);

        $entity = $this->renewableService->getOrNull($this->id);

        $this->assertEquals($this->id, $entity->getId());
        $this->assertEquals($this->entityProvider()["name"], $entity->getName());
        $entity->setDeletor("Test");
        $this->em->flush($entity);

        $entity = $this->renewableService->getOrNull($this->id);
        $this->assertNull($entity);
    }

    public function testPatch(): void
    {
        $this->createEntity($this->entityProvider(), TestEntity::class);

        $this->service->patch($this->id, "name", $this->secondaryEntityProvider()["name"]);

        $entity = $this->em
            ->getRepository(TestEntity::class)
            ->find($this->id);
        $this->assertEquals($this->secondaryEntityProvider()["name"], $entity->getName());
    }

    public function testDelete(): void
    {
        $this->createEntity($this->entityProvider(), TestEntity::class);

        $this->service->delete($this->id);

        $result = $this->em
            ->getRepository(TestEntity::class)
            ->find($this->id);
        $this->assertNull($result);
    }

    public function testDeleteRenewableEntity(): void
    {
        $this->createEntity($this->entityProvider(), RenewableTestEntity::class);

        $this->renewableService->delete($this->id);

        $entity = $this->em
            ->getRepository(RenewableTestEntity::class)
            ->find($this->id);
        $this->assertTrue($entity->isDeleted());
    }

    public function testUndelete(): void
    {
        $this->createEntity($this->entityProvider(), RenewableTestEntity::class);
        $entity = $this->em->find(RenewableTestEntity::class, $this->id);
        $entity->setDeleted();
        $this->em->flush($entity);
        $this->assertTrue($entity->isDeleted());

        $this->renewableService->undelete($this->id);
        $entity = $this->em->find(RenewableTestEntity::class, $this->id);
        $this->assertFalse($entity->isDeleted());
    }

    public function testPurge(): void
    {
        $this->createEntity($this->entityProvider(), RenewableTestEntity::class);
        $entity = $this->em->find(RenewableTestEntity::class, $this->id);
        $entity->setDeleted();
        $this->em->flush($entity);

        $this->renewableService->purge($this->id);
        $entity = $this->em->find(RenewableTestEntity::class, $this->id);
        $this->assertNull($entity);
    }

    public function testGetQueryBuilder(): void
    {
        $qb = $this->service->getQueryBuilder();
        $this->assertEquals(TestEntity::class, $qb->getRootEntities()[0]);
        $this->assertEquals(self::ENTITY_ALIAS, $qb->getRootAliases()[0]);
    }

    public function testGetCountQueryBuilder(): void
    {
        $qb = $this->service->getCountQueryBuilder(TestEntity::ID);
        $this->assertEquals(TestEntity::class, $qb->getRootEntities()[0]);
        $this->assertEquals(self::ENTITY_ALIAS, $qb->getRootAliases()[0]);
        $this->assertTrue(
            Strings::contains(
                $qb->getDQL(),
                "COUNT(" .
                AbstractDoctrineServiceTest::ENTITY_ALIAS . "." . TestEntity::ID .
                ") FROM " . TestEntity::class . " " . self::ENTITY_ALIAS
            )
        );
    }

    public function testGetSumQueryBuilder(): void
    {
        $qb = $this->service->getSumQueryBuilder(TestEntity::ID);
        $this->assertEquals(TestEntity::class, $qb->getRootEntities()[0]);
        $this->assertEquals(self::ENTITY_ALIAS, $qb->getRootAliases()[0]);
        $this->assertTrue(
            Strings::contains(
                $qb->getDQL(),
                "SUM(" .
                AbstractDoctrineServiceTest::ENTITY_ALIAS . "." . TestEntity::ID .
                ") FROM " . TestEntity::class . " " . self::ENTITY_ALIAS
            )
        );
    }

    public function testGetAvgQueryBuilder(): void
    {
        $qb = $this->service->getAvgQueryBuilder(TestEntity::ID);
        $this->assertEquals(TestEntity::class, $qb->getRootEntities()[0]);
        $this->assertEquals(self::ENTITY_ALIAS, $qb->getRootAliases()[0]);
        $this->assertTrue(
            Strings::contains(
                $qb->getDQL(),
                "AVG(" .
                AbstractDoctrineServiceTest::ENTITY_ALIAS . "." . TestEntity::ID .
                ") FROM " . TestEntity::class . " " . self::ENTITY_ALIAS
            )
        );
    }

    public function testGetMinQueryBuilder(): void
    {
        $qb = $this->service->getMinQueryBuilder(TestEntity::ID);
        $this->assertEquals(TestEntity::class, $qb->getRootEntities()[0]);
        $this->assertEquals(self::ENTITY_ALIAS, $qb->getRootAliases()[0]);
        $this->assertTrue(
            Strings::contains(
                $qb->getDQL(),
                "MIN(" .
                AbstractDoctrineServiceTest::ENTITY_ALIAS . "." . TestEntity::ID .
                ") FROM " . TestEntity::class . " " . self::ENTITY_ALIAS
            )
        );
    }

    public function testGetMaxQueryBuilder(): void
    {
        $qb = $this->service->getMaxQueryBuilder(TestEntity::ID);
        $this->assertEquals(TestEntity::class, $qb->getRootEntities()[0]);
        $this->assertEquals(self::ENTITY_ALIAS, $qb->getRootAliases()[0]);
        $this->assertTrue(
            Strings::contains(
                $qb->getDQL(),
                "MAX(" .
                AbstractDoctrineServiceTest::ENTITY_ALIAS . "." . TestEntity::ID .
                ") FROM " . TestEntity::class . " " . self::ENTITY_ALIAS
            )
        );
    }
}
