<?php

namespace App\Dto;

use App\Entity\Project;

class Task
{
    private ?string $id = null;
    private ?string $label = null;
    private ?float $optimistic = null;
    private ?float $likely = null;
    private ?float $pessimistic = null;
    private ?string $category = null;
    private ?Project $project = null;

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

    public function getOptimistic(): ?float
    {
        return $this->optimistic;
    }

    public function setOptimistic(?float $optimistic): static
    {
        $this->optimistic = $optimistic;

        return $this;
    }

    public function getLikely(): ?float
    {
        return $this->likely;
    }

    public function setLikely(?float $likely): static
    {
        $this->likely = $likely;

        return $this;
    }

    public function getPessimistic(): ?float
    {
        return $this->pessimistic;
    }

    public function setPessimistic(?float $pessimistic): static
    {
        $this->pessimistic = $pessimistic;

        return $this;
    }

    public function getCategory(): ?string
    {
        return $this->category;
    }

    public function setCategory(?string $category): static
    {
        $this->category = $category;

        return $this;
    }

    public function getProject(): ?Project
    {
        return $this->project;
    }

    public function setProject(?Project $project): static
    {
        $this->project = $project;

        return $this;
    }
}
