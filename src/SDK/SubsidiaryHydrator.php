<?php

namespace BayWaReLusy\UsersAPI\SDK;

use Laminas\Hydrator\ClassMethodsHydrator;
use Laminas\Hydrator\HydratorInterface;

class SubsidiaryHydrator implements HydratorInterface
{
    private ClassMethodsHydrator $hydrator;

    public function __construct()
    {
        $this->hydrator = new ClassMethodsHydrator(false);
    }

    /**
     * @param array<mixed> $data
     * @param SubsidiaryEntity $object
     * @return SubsidiaryEntity
     */
    public function hydrate(array $data, SubsidiaryEntity $object): SubsidiaryEntity
    {
        return $this->hydrator->hydrate($data, $object);
    }

    /**
     * @param object $object
     * @return array<mixed>
     */
    public function extract(object $object): array
    {
        $data = $this->hydrator->extract($object);
        return $data;
    }
}
