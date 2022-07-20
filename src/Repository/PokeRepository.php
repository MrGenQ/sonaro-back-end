<?php

namespace App\Repository;

use App\Entity\Poke;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Poke>
 *
 * @method Poke|null find($id, $lockMode = null, $lockVersion = null)
 * @method Poke|null findOneBy(array $criteria, array $orderBy = null)
 * @method Poke[]    findAll()
 * @method Poke[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PokeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Poke::class);
    }

    public function add(Poke $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Poke $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
