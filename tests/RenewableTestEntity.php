<?php

namespace Monderka\DoctrineTools\Test;

use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Monderka\DoctrineTools\Interfaces\RenewableDoctrineEntityInterface;

#[ORM\Entity]
#[ORM\Table(name: "renewable_test_table")]
class RenewableTestEntity implements RenewableDoctrineEntityInterface
{
    public const NAME = "name";
    public const ID = "id";
    public const CREATE_SQL =
        "CREATE TABLE renewable_test_table (id INTEGER PRIMARY KEY, deletor varchar(50) DEFAULT 0, name varchar(150) NULL);";
    public const DROP_SQL = "DROP TABLE IF EXISTS renewable_test_table";

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

    #[ORM\Column(type: "string", length: 50, nullable: true)]
    protected ?string $deletor = null;

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

    public function setDeletor(string $deletor): self
    {
        $this->deletor = $deletor;
        return $this;
    }

    public function getDeletor(): ?string
    {
        return $this->deletor;
    }

    public function getDeleted(): ?DateTime
    {
        return null;
    }

    public function renew(): void
    {
        $this->deletor = null;
    }

    public function setDeleted(): void
    {
        $this->deletor = "deleted";
    }

    public function isDeleted(): bool
    {
        return $this->deletor !== null;
    }
}
