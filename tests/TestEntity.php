<?php

namespace Monderka\DoctrineTools\Test;

use Doctrine\ORM\Mapping as ORM;
use Monderka\DoctrineTools\Interfaces\DoctrineEntityInterface;

#[ORM\Entity]
#[ORM\Table(name: "test_table")]
class TestEntity implements DoctrineEntityInterface
{
    public const NAME = "name";
    public const ID = "id";
    public const CREATE_SQL = "CREATE TABLE test_table (id INTEGER PRIMARY KEY,name varchar(150) NULL);";
    public const DROP_SQL = "DROP TABLE IF EXISTS test_table";

    public function setId(int $id): self
    {
        $this->id = $id;
        return $this;
    }

    #[ORM\Column(type: "integer", nullable: false)]
    #[ORM\Id]
    #[ORM\GeneratedValue]
    protected ?int $id = null;

    #[ORM\Column(type: "string", length: 150, nullable: true)]
    protected ?string $name = null;

    public function getId(): int
    {
        return $this->id ?? 0;
    }

    public function __clone()
    {
        $this->id = null;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): self
    {
        $this->name = $name;
        return $this;
    }
}
