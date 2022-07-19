<?php

namespace App\Entity;

use ApiPlatform\Core\Annotation\ApiResource;
use App\Repository\PokeRepository;
use Doctrine\ORM\Mapping as ORM;
#[ORM\Entity(repositoryClass: PokeRepository::class)]
#[ApiResource]
class Poke
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\Column(type: 'string', length: 255)]
    private $sender;

    #[ORM\Column(type: 'string', length: 255)]
    private $recipient;

    #[ORM\Column(type: 'date', nullable: true)]
    private $dateTime;
    public function __construct()
    {
        $this->dateTime = new \DateTime();
    }
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSender(): ?string
    {
        return $this->sender;
    }

    public function setSender(string $sender): self
    {
        $this->sender = $sender;

        return $this;
    }

    public function getRecipient(): ?string
    {
        return $this->recipient;
    }

    public function setRecipient(string $recipient): self
    {
        $this->recipient = $recipient;

        return $this;
    }

    public function getDateTime(): ?\DateTimeInterface
    {
        return $this->dateTime;
    }

    public function setDateTime(\DateTimeInterface $dateTime): self
    {
        $this->dateTime = $dateTime;

        return $this;
    }
}
