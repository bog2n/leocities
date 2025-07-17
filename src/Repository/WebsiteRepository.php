<?php

namespace App\Repository;

use App\Entity\Website;
use App\Entity\User;
use Doctrine\ORM\Query\ResultSetMappingBuilder;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Website>
 */
class WebsiteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Website::class);
    }

    /**
     * @return Website[] Returns at most $num random websites
     */
    public function getRandomWebsites($num)
    {
        $rsm = new ResultSetMappingBuilder($this->getEntityManager());
        $rsm->addRootEntityFromClassMetadata(User::class, 'u');
        $rsm->addJoinedEntityFromClassMetadata(Website::class, 'w', 'u', 'website', ['id' => 'w_id']);

        $query = $this->getEntityManager()->createNativeQuery(
            'with w as (
                select id, description
                from website
                order by random()
                limit ?
            )
            select '.$rsm.'
            from "user" u
            join w on u.website_id = w.id
            order by random()',
            $rsm)->setParameter(1, $num);

        return $query->getResult();
    }

//    /**
//     * @return Website[] Returns an array of Website objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('w')
//            ->andWhere('w.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('w.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Website
//    {
//        return $this->createQueryBuilder('w')
//            ->andWhere('w.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
