<?php

namespace App\Entity;

use App\Dto\Category;
use App\Repository\CustomerRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CustomerRepository::class)]
class Customer
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    /**
     * @var Collection<int, Project>
     */
    #[ORM\OneToMany(targetEntity: Project::class, mappedBy: 'customer', orphanRemoval: true)]
    private Collection $projects;

    /**
     * @var Collection<int, User>
     */
    #[ORM\ManyToMany(targetEntity: User::class, inversedBy: 'customers')]
    private Collection $users;

    /**
     * @var Collection<int, Category>
     */
    private Collection $categorys;

    public function __construct()
    {
        $this->projects = new ArrayCollection();
        $this->users = new ArrayCollection();
        $this->categorys = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    /**
     * @return Collection<int, Project>
     */
    public function getProjects(): Collection
    {
        return $this->projects;
    }

    public function addProject(Project $project): static
    {
        if (!$this->projects->contains($project)) {
            $this->projects->add($project);
            $project->setCustomer($this);
        }

        return $this;
    }

    public function removeProject(Project $project): static
    {
        if ($this->projects->removeElement($project)) {
            // set the owning side to null (unless already changed)
            if ($project->getCustomer() === $this) {
                $project->setCustomer(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, User>
     */
    public function getUsers(): Collection
    {
        return $this->users;
    }

    public function addUser(User $user): static
    {
        if (!$this->users->contains($user)) {
            $this->users->add($user);
        }

        return $this;
    }

    public function removeUser(User $user): static
    {
        $this->users->removeElement($user);

        return $this;
    }

    /**
     * @return Collection<int, Category>
     */
    public function getCategorys(): Collection
    {
        if (!isset($this->categorys)) {
            $this->categorys = new ArrayCollection();
        }

        return $this->categorys;
    }

    public function addCategory(Category $category): static
    {
        if (!isset($this->categorys)) {
            $this->categorys = new ArrayCollection();
        }
        if (!$this->categorys->contains($category)) {
            $this->categorys->add($category);
            $category->setCustomer($this);
        }

        return $this;
    }

    public function removeCategory(Category $category): static
    {
        if (!isset($this->categorys)) {
            $this->categorys = new ArrayCollection();
        }
        if ($this->categorys->removeElement($category)) {
            if ($category->getCustomer() === $this) {
                $category->setCustomer(null);
            }
        }

        return $this;
    }
}
