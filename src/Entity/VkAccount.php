<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\VkAccountRepository")
 */
class VkAccount extends SocialAccount
{
    /**
     * @ORM\ManyToMany(targetEntity="App\Entity\User", mappedBy="vkAccounts")
     */
    private $users;

    /**
     * VkAccount constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->users = new ArrayCollection();
    }

    /**
     * @return Collection\User[]
     */
    public function getUsers(): Collection
    {
        return $this->users;
    }

    /**
     * @return string
     */
    public function getLink(): string
    {
        return "https://vk.com/id{$this->getExternalId()}";
    }
}