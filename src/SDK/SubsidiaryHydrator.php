<?php

namespace BayWaReLusy\UsersAPI\SDK;

use Laminas\Hydrator\ClassMethodsHydrator;

class SubsidiaryHydrator
{
    private ClassMethodsHydrator $hydrator;

    public function __construct()
    {
        $this->hydrator = new ClassMethodsHydrator(false);
    }

    public function hydrate(array $data, object $object): object
    {
        return $this->hydrator->hydrate($data, $object);
    }

    public function extract(object $object): array
    {
        $data = $this->hydrator->extract($object);
        return $data;
    }
}
