<?php

namespace Monderka\DoctrineTools\Interfaces;

interface GraphQLEntityInterface extends DoctrineEntityInterface
{
    /** @return array<string, mixed> */
    public function toGraphQLOutput(): array;

    /**
     * @param array<string, mixed> $parameters
     * @param array<string, mixed> $associatedEntities
     * @param array<string, mixed> $options
     * @return self
     */
    public static function createFromGraphQLInput(
        array $parameters,
        array $associatedEntities,
        array $options
    ): self;

    /**
     * @param array<string, mixed> $parameters
     * @param array<string, mixed> $associatedEntities
     * @param array<string, mixed> $options
     * @return self
     */
    public function updateFromGraphQLInput(
        array $parameters,
        array $associatedEntities,
        array $options
    ): self;
}
