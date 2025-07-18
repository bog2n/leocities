<?php

namespace App\Entity;

use App\Repository\DirRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DirRepository::class)]
class Dir
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'dir', cascade: ['persist', 'remove'])]
    private ?Inode $parent = null;

    /**
     * @var Collection<int, Inode>
     */
    #[ORM\OneToMany(targetEntity: Inode::class, mappedBy: 'parent')]
    #[ORM\OrderBy(['name' => 'ASC'])]
    private Collection $child;

    public function __construct()
    {
        $this->child = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getParent(): ?Inode
    {
        return $this->parent;
    }

    public function setParent(?Inode $parent): static
    {
        $this->parent = $parent;

        return $this;
    }

    /**
     * @return Collection<int, Inode>
     */
    public function getChild(): Collection
    {
        return $this->child;
    }

    public function addChild(Inode $child): static
    {
        if (!$this->child->contains($child)) {
            $this->child->add($child);
            $child->setParent($this);
        }

        return $this;
    }

    public function removeChild(Inode $child): static
    {
        if ($this->child->removeElement($child)) {
            // set the owning side to null (unless already changed)
            if ($child->getParent() === $this) {
                $child->setParent(null);
            }
        }

        return $this;
    }
}
