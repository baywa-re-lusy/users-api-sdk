<?php

namespace BayWaReLusy\UsersAPI\SDK;

use Laminas\Permissions\Acl\Resource\ResourceInterface;

/**
 * Class UserEntity
 */
class SubsidiaryEntity implements SubsidiaryInterface, ResourceInterface
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
    public function getResourceId(): string
    {
        return 'subsidiary_' . $this->getId();
    }
}
