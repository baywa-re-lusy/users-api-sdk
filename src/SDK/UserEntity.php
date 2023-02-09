<?php

namespace BayWaReLusy\UsersAPI\SDK;

use BayWaReLusy\UserManagement\UserInterface;

/**
 * Class UserEntity
 */
class UserEntity implements UserInterface
{
    protected string $id;
    protected string $username;
    protected string $email;
    protected bool $emailVerified;
    protected ?\DateTime $created;
    /** @var string[] */
    protected array $roles = [];

    /**
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @param string $id
     * @return UserEntity
     */
    public function setId(string $id): UserEntity
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return string
     */
    public function getUsername(): string
    {
        return $this->username;
    }

    /**
     * @param string $username
     * @return UserEntity
     */
    public function setUsername(string $username): UserEntity
    {
        $this->username = $username;
        return $this;
    }

    /**
     * @return string
     */
    public function getEmail(): string
    {
        return $this->email;
    }

    /**
     * @param string $email
     * @return UserEntity
     */
    public function setEmail(string $email): UserEntity
    {
        $this->email = $email;
        return $this;
    }

    /**
     * @return bool
     */
    public function getEmailVerified(): bool
    {
        return $this->emailVerified;
    }

    /**
     * @param bool $emailVerified
     * @return UserEntity
     */
    public function setEmailVerified(bool $emailVerified): UserEntity
    {
        $this->emailVerified = $emailVerified;
        return $this;
    }

    /**
     * @return \DateTime|null
     */
    public function getCreated(): ?\DateTime
    {
        return $this->created;
    }

    /**
     * @param \DateTime|null $created
     * @return UserEntity
     */
    public function setCreated(?\DateTime $created): UserEntity
    {
        $this->created = $created;
        return $this;
    }

    /**
     * @return string[]
     */
    public function getRoles(): array
    {
        return $this->roles;
    }

    /**
     * @param string[] $roles
     * @return UserEntity
     */
    public function setRoles(array $roles): UserEntity
    {
        $this->roles = $roles;
        return $this;
    }

    /**
     * @param string $role
     * @return UserEntity
     */
    public function addRole(string $role): UserEntity
    {
        if (!in_array($role, $this->roles)) {
            $this->roles[] = $role;
        }

        return $this;
    }
}
