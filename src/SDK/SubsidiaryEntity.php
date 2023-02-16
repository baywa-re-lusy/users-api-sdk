<?php

namespace BayWaReLusy\UsersAPI\SDK;

/**
 * Class UserEntity
 */
class SubsidiaryEntity implements SubsidiaryInterface
{
    protected string $id;
    protected string $name;

    /**
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @param string $id
     * @return SubsidiaryEntity
     */
    public function setId(string $id): SubsidiaryEntity
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param string $name
     * @return SubsidiaryEntity
     */
    public function setName(string $name): SubsidiaryEntity
    {
        $this->name = $name;
        return $this;
    }
}
