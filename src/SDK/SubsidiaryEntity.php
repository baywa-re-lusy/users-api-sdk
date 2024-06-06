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
    protected ?string $footer = null;
    protected ?string $street = null;
    protected ?string $number = null;
    protected ?string $zipCode = null;
    protected ?string $city = null;
    protected ?string $country = null;
    protected ?int $cepReadyThreshold = null;

    /**
     * @inheritDoc
     */
    public function getResourceId(): string
    {
        return 'subsidiary_' . $this->getId();
    }

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
     * @return string|null
     */
    public function getFooter(): ?string
    {
        return $this->footer;
    }

    /**
     * @param string|null $footer
     * @return SubsidiaryEntity
     */
    public function setFooter(?string $footer): SubsidiaryEntity
    {
        $this->footer = $footer;
        return $this;
    }

    public function getStreet(): ?string
    {
        return $this->street;
    }

    public function setStreet(?string $street): SubsidiaryEntity
    {
        $this->street = $street;
        return $this;
    }

    public function getNumber(): ?string
    {
        return $this->number;
    }

    public function setNumber(?string $number): SubsidiaryEntity
    {
        $this->number = $number;
        return $this;
    }

    public function getZipCode(): ?string
    {
        return $this->zipCode;
    }

    public function setZipCode(?string $zipCode): SubsidiaryEntity
    {
        $this->zipCode = $zipCode;
        return $this;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(?string $city): SubsidiaryEntity
    {
        $this->city = $city;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getCountry(): ?string
    {
        return $this->country;
    }

    /**
     * @param string|null $country
     * @return SubsidiaryEntity
     */
    public function setCountry(?string $country): SubsidiaryEntity
    {
        $this->country = $country;
        return $this;
    }

    public function getCepReadyThreshold(): ?int
    {
        return $this->cepReadyThreshold;
    }

    public function setCepReadyThreshold(?int $cepReadyThreshold): SubsidiaryEntity
    {
        $this->cepReadyThreshold = $cepReadyThreshold;
        return $this;
    }
}
