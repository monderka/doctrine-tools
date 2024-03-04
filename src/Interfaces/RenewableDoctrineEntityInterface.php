<?php

namespace Monderka\DoctrineTools\Interfaces;

use DateTime;

interface RenewableDoctrineEntityInterface extends DoctrineEntityInterface
{
    public function renew(): void;
    public function setDeleted(): void;
    public function isDeleted(): bool;
}
