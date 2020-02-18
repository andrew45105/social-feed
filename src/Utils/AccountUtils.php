<?php

namespace App\Utils;

use App\Entity\InstagramAccount;
use App\Entity\VkAccount;

/**
 * Class AccountUtils
 * @package App\Utils
 */
class AccountUtils
{
    const ACCOUNT_TYPE_INSTAGRAM    = 'instagram';
    const ACCOUNT_TYPE_VK           = 'vk';

    /**
     * Get account type by object (instagram / vk)
     *
     * @param InstagramAccount|VkAccount $account
     *
     * @return string
     */
    public static function getAccountTypeByObject($account)
    {
        return $account instanceof InstagramAccount ?
            self::ACCOUNT_TYPE_INSTAGRAM : self::ACCOUNT_TYPE_VK;
    }

    /**
     * Get account object by type
     *
     * @param $type
     *
     * @return InstagramAccount|VkAccount
     */
    public static function getAccountObjectByType(string $type)
    {
        self::checkType($type);
        return $type === self::ACCOUNT_TYPE_INSTAGRAM ?
            new InstagramAccount() : new VkAccount();
    }

    /**
     * Get account class name by type
     *
     * @param string $type
     *
     * @return string
     */
    public static function getAccountClassByType(string $type)
    {
        self::checkType($type);
        return $type === self::ACCOUNT_TYPE_INSTAGRAM ?
            InstagramAccount::class : VkAccount::class;
    }

    /**
     * Check account type
     *
     * @param $type
     */
    public static function checkType($type)
    {
        if (!in_array($type, [
            self::ACCOUNT_TYPE_INSTAGRAM,
            self::ACCOUNT_TYPE_VK
        ])) {
            throw new \RuntimeException('Wrong account type: ' . $type);
        }
    }

    /**
     * Check account exists in data file
     *
     * @param array                         $accountsFile
     * @param InstagramAccount|VkAccount    $account
     *
     * @return bool
     */
    public static function existInFile(array $accountsFile, $account)
    {
        foreach ($accountsFile as $accountData) {
            $match =
                $accountData[0] === $account->getExternalId() &&
                $accountData[2] === AccountUtils::getAccountTypeByObject($account);
            if ($match) {
                return true;
            }
        }
        return false;
    }
}