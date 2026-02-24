<?php

namespace App\Dto;

use App\Entity\Customer;
use Ramsey\Uuid\Uuid;

class Category
{
    private ?string $id = null;
    private ?string $label = null;
    private ?float $costPerTimeUnit = null;
    private ?Customer $customer = null;

    public function __construct()
    {
        $this->id = Uuid::uuid4()->toString();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setId(?string $id): static
    {
        $this->id = $id;

        return $this;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(?string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function getCostPerTimeUnit(): ?float
    {
        return $this->costPerTimeUnit;
    }

    public function setCostPerTimeUnit(?float $costPerTimeUnit): static
    {
        $this->costPerTimeUnit = $costPerTimeUnit;

        return $this;
    }

    public function getCustomer(): ?Customer
    {
        return $this->customer;
    }

    public function setCustomer(?Customer $customer): static
    {
        $this->customer = $customer;

        return $this;
    }
}
