<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use FOS\UserBundle\Model\User as BaseUser;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="user")
 */
class User extends BaseUser
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @ORM\ManyToMany(targetEntity="App\Entity\InstagramAccount", inversedBy="users")
     * @ORM\OrderBy({"username" = "ASC"})
     */
    private $instagramAccounts;

    /**
     * @ORM\ManyToMany(targetEntity="App\Entity\VkAccount", inversedBy="users")
     * @ORM\OrderBy({"username" = "ASC"})
     */
    private $vkAccounts;

    /**
     * User constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->instagramAccounts = new ArrayCollection();
        $this->vkAccounts = new ArrayCollection();
    }

    /**
     * @return Collection|InstagramAccount[]
     */
    public function getInstagramAccounts(): Collection
    {
        return $this->instagramAccounts;
    }

    /**
     * @return Collection|VkAccount[]
     */
    public function getVkAccounts(): Collection
    {
        return $this->vkAccounts;
    }

    /**
     * @param InstagramAccount $account
     * @return $this
     */
    public function addInstagramAccount(InstagramAccount $account): self
    {
        if (!$this->instagramAccounts->contains($account)) {
            $this->instagramAccounts[] = $account;
        }
        return $this;
    }

    /**
     * @param InstagramAccount $account
     * @return User
     */
    public function removeInstagramAccount(InstagramAccount $account): self
    {
        if ($this->instagramAccounts->contains($account)) {
            $this->instagramAccounts->removeElement($account);
        }
        return $this;
    }

    /**
     * @param InstagramAccount $account
     * @return bool
     */
    public function hasInstagramAccount(InstagramAccount $account): bool
    {
        return $this->instagramAccounts->contains($account);
    }

    /**
     * @param VkAccount $account
     * @return $this
     */
    public function addVkAccount(VkAccount $account): self
    {
        if (!$this->vkAccounts->contains($account)) {
            $this->vkAccounts[] = $account;
        }
        return $this;
    }

    /**
     * @param VkAccount $account
     * @return User
     */
    public function removeVkAccount(VkAccount $account): self
    {
        if ($this->vkAccounts->contains($account)) {
            $this->vkAccounts->removeElement($account);
        }
        return $this;
    }

    /**
     * @param VkAccount $account
     * @return bool
     */
    public function hasVkAccount(VkAccount $account): bool
    {
        return $this->vkAccounts->contains($account);
    }
}