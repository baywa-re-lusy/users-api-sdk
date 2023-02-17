<?php

namespace BayWaReLusy\UsersAPI\SDK;

interface SubsidiaryInterface
{
    /**
     * @return string
     */
    public function getId(): string;

    /**
     * @param string $id
     * @return SubsidiaryInterface
     */
    public function setId(string $id): SubsidiaryInterface;

    /**
     * @return string
     */
    public function getName(): string;

    /**
     * @param string $name
     * @return SubsidiaryInterface
     */
    public function setName(string $name): SubsidiaryInterface;
}
