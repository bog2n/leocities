<?php

namespace App\Entity;

use App\Repository\InodeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InodeRepository::class)]
#[ORM\UniqueConstraint(fields: ["parent", "name"])]
class Inode
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $name = null;

    /**
     * @var Collection<int, Extent>
     */
    #[ORM\OneToMany(targetEntity: Extent::class, mappedBy: 'inode')]
    private Collection $extent;

    #[ORM\OneToOne(mappedBy: 'parent', cascade: ['persist', 'remove'])]
    private ?Dir $dir = null;

    #[ORM\ManyToOne(inversedBy: 'child')]
    private ?Dir $parent = null;

    #[ORM\ManyToOne(inversedBy: 'inodes')]
    private ?User $owner = null;

    public function __construct(User $user)
    {
        $this->extent = new ArrayCollection();
        $this->owner = $user;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): static
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return Collection<int, Extent>
     */
    public function getExtent(): Collection
    {
        return $this->extent;
    }

    public function addExtent(Extent $extent): static
    {
        if (!$this->extent->contains($extent)) {
            $this->extent->add($extent);
            $extent->setInode($this);
        }

        return $this;
    }

    public function removeExtent(Extent $extent): static
    {
        if ($this->extent->removeElement($extent)) {
            // set the owning side to null (unless already changed)
            if ($extent->getInode() === $this) {
                $extent->setInode(null);
            }
        }

        return $this;
    }

    public function getDir(): ?Dir
    {
        return $this->dir;
    }

    public function setDir(?Dir $dir): static
    {
        // unset the owning side of the relation if necessary
        if ($dir === null && $this->dir !== null) {
            $this->dir->setParent(null);
        }

        // set the owning side of the relation if necessary
        if ($dir !== null && $dir->getParent() !== $this) {
            $dir->setParent($this);
        }

        $this->dir = $dir;

        return $this;
    }

    public function isDir(): bool
    {
        return $this->getDir() !== null;
    }

    public function getParent(): ?Dir
    {
        return $this->parent;
    }

    public function setParent(?Dir $parent): static
    {
        $this->parent = $parent;

        return $this;
    }

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function setOwner(?User $owner): static
    {
        $this->owner = $owner;

        return $this;
    }
}
