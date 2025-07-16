<?php

namespace App\Entity;

use App\Repository\ExtentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ExtentRepository::class)]
class Extent
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?int $start = null;

    #[ORM\Column(type: Types::BIGINT)]
    private ?string $length = null;

    #[ORM\ManyToOne(inversedBy: 'extent')]
    private ?Inode $inode = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getStart(): ?int
    {
        return $this->start;
    }

    public function setStart(int $start): static
    {
        $this->start = $start;

        return $this;
    }

    public function getLength(): ?string
    {
        return $this->length;
    }

    public function setLength(string $length): static
    {
        $this->length = $length;

        return $this;
    }

    public function getInode(): ?Inode
    {
        return $this->inode;
    }

    public function setInode(?Inode $inode): static
    {
        $this->inode = $inode;

        return $this;
    }
}
