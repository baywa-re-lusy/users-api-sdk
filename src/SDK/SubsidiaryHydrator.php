<?php

namespace BayWaReLusy\UsersAPI\SDK;

use Laminas\Hydrator\ClassMethodsHydrator;

/**
 * @method SubsidiaryEntity hydrate(array $data, object $object)
 */
class SubsidiaryHydrator extends ClassMethodsHydrator
{
    public function __construct()
    {
        parent::__construct(false);
    }
}
