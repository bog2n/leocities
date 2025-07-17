<?php

namespace App\Repository;

use App\Entity\Inode;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Inode>
 */
class InodeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Inode::class);
    }

    public function getLength($inode_id)
    {
        $result = $this->getEntityManager()
                       ->getConnection()
                       ->fetchNumeric('SELECT sum(length) FROM extent
                       WHERE inode_id = ?', [
                           1 => $inode_id
                       ]);

        return $result[0] ?? -1;
    }

    //    /**
    //     * @return Inode[] Returns an array of Inode objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('i')
    //            ->andWhere('i.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('i.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Inode
    //    {
    //        return $this->createQueryBuilder('i')
    //            ->andWhere('i.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
