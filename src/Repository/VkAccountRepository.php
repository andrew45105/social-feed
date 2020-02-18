<?php

namespace App\Repository;

use App\Entity\VkAccount;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Persistence\ManagerRegistry;

/**
 * @method VkAccount|null find($id, $lockMode = null, $lockVersion = null)
 * @method VkAccount|null findOneBy(array $criteria, array $orderBy = null)
 * @method VkAccount[]    findAll()
 * @method VkAccount[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class VkAccountRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, VKAccount::class);
    }

    // /**
    //  * @return VkAccount[] Returns an array of VkAccount objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('i.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?VkAccount
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */

    /**
     * Add all accounts to user
     *
     * @param int   $userId
     * @param array $accountIds
     */
    public function addAllToUser(int $userId, array $accountIds)
    {
        if ($accountIds) {
            $tableName = 'user_vk_account';
            $template = "INSERT INTO $tableName (user_id, vk_account_id) VALUES ";
            foreach ($accountIds as $accountId) {
                $value = "($userId, $accountId),";
                $template .= $value;
            }
            $template = rtrim($template, ',');
            $template .= " ON DUPLICATE KEY UPDATE $tableName.vk_account_id = VALUES(vk_account_id);";
            /** @var \PDO $conn */
            $conn = $this->getEntityManager()->getConnection();
            $query = $conn->prepare($template);
            $query->execute();
        }
    }

    /**
     * Delete all accounts from user
     *
     * @param int $userId
     */
    public function deleteAllFromUser(int $userId)
    {
        /** @var \PDO $conn */
        $conn = $this->getEntityManager()->getConnection();

        $delTemplate = "DELETE FROM user_vk_account WHERE user_id = $userId";
        $query = $conn->prepare($delTemplate);
        $query->execute();
    }
}