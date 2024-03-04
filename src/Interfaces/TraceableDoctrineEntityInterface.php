<?php

namespace Monderka\DoctrineTools\Interfaces;

use DateTime;

interface TraceableDoctrineEntityInterface extends RenewableDoctrineEntityInterface
{
    public function setCreator(string $creator): TraceableDoctrineEntityInterface;
    public function setModifior(string $modifior): TraceableDoctrineEntityInterface;
    public function getCreator(): ?string;
    public function getModifior(): ?string;
    public function getModified(): ?DateTime;
    public function getCreated(): ?DateTime;
    public function setDeletor(string $deletor): TraceableDoctrineEntityInterface;
    public function getDeletor(): ?string;
    public function getDeleted(): ?DateTime;
}
