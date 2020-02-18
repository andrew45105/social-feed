<?php

namespace App\Repository;

use App\Entity\InstagramAccount;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Persistence\ManagerRegistry;

/**
 * @method InstagramAccount|null find($id, $lockMode = null, $lockVersion = null)
 * @method InstagramAccount|null findOneBy(array $criteria, array $orderBy = null)
 * @method InstagramAccount[]    findAll()
 * @method InstagramAccount[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class InstagramAccountRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InstagramAccount::class);
    }

    // /**
    //  * @return InstagramAccount[] Returns an array of InstagramAccount objects
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
    public function findOneBySomeField($value): ?InstagramAccount
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
            $tableName = 'user_instagram_account';
            $template = "INSERT INTO $tableName (user_id, instagram_account_id) VALUES ";
            foreach ($accountIds as $accountId) {
                $value = "($userId, $accountId),";
                $template .= $value;
            }
            $template = rtrim($template, ',');
            $template .= " ON DUPLICATE KEY UPDATE $tableName.instagram_account_id = VALUES(instagram_account_id);";
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

        $delTemplate = "DELETE FROM user_instagram_account WHERE user_id = $userId";
        $query = $conn->prepare($delTemplate);
        $query->execute();
    }
}
