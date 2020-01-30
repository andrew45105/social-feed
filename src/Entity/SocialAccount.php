<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\MappedSuperclass
 */
abstract class SocialAccount
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    protected $id;

    /**
     * @ORM\Column(type="string", length=32)
     */
    protected $externalId;

    /**
     * @ORM\Column(type="string", length=255)
     */
    protected $username;

    /**
     * @var boolean
     * @ORM\Column(name="need_update", type="boolean", nullable=false, options={"default" : "0"})
     */
    protected $needUpdate;

    /**
     * InstagramAccount constructor.
     */
    public function __construct()
    {
        $this->needUpdate = false;
    }

    /**
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @return null|string
     */
    public function getExternalId(): ?string
    {
        return $this->externalId;
    }

    /**
     * @param string $externalId
     * @return InstagramAccount
     */
    public function setExternalId(string $externalId): self
    {
        $this->externalId = $externalId;

        return $this;
    }

    /**
     * @return null|string
     */
    public function getUsername(): ?string
    {
        return $this->username;
    }

    /**
     * @param string $username
     * @return InstagramAccount
     */
    public function setUsername(string $username): self
    {
        $this->username = $username;

        return $this;
    }

    /**
     * @return bool
     */
    public function isNeedUpdate(): bool
    {
        return $this->needUpdate;
    }

    /**
     * @param bool $needUpdate
     * @return InstagramAccount
     */
    public function setNeedUpdate(bool $needUpdate): self
    {
        $this->needUpdate = $needUpdate;

        return $this;
    }

    /**
     * @return string
     */
    abstract public function getLink(): string;
}