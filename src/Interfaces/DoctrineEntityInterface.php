<?php

namespace Monderka\DoctrineTools\Interfaces;

interface DoctrineEntityInterface
{
    public function getId(): int|string;
    public function __clone();
}
