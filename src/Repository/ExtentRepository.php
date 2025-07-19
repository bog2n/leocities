<?php

namespace App\Repository;

use App\Entity\Extent;
use App\Service\Fs\Consts;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\Query\ResultSetMappingBuilder;

/**
 * @extends ServiceEntityRepository<Extent>
 */
class ExtentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, private $block_file)
    {
        parent::__construct($registry, Extent::class);
    }

    /**
     * Returns biggest possible extent in block file
     */
    public function getFreeExtent(): Extent
    {
        $result = $this->getEntityManager()
                       ->getConnection()
                       ->fetchNumeric('SELECT max(
                           start + ceil(length::real / ?)
                       ) FROM extent', [
                           1 => Consts::BLOCK_SIZE
                       ]);

        $result[0] ??= 0;

        $out = new Extent;
        $out->setStart($result[0]);
        // get free bytes till end of the file
        $out->setLength(filesize($this->block_file) - $result[0] * Consts::BLOCK_SIZE);

        return $out;
    }

    //    /**
    //     * @return Extent[] Returns an array of Extent objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('e')
    //            ->andWhere('e.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('e.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Extent
    //    {
    //        return $this->createQueryBuilder('e')
    //            ->andWhere('e.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
