<?php

namespace BayWaReLusy\UsersAPI\SDK;

use Laminas\Permissions\Acl\Role\RoleInterface;

/**
 * Class UserEntity
 */
class SubsidiaryEntity implements SubsidiaryInterface, RoleInterface
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

    /**
     * @inheritDoc
     */
    public function getRoleId(): string
    {
        return 'subsidiary_' . $this->getId();
    }
}
