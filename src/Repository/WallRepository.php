<?php

namespace App\Repository;

use App\Entity\Wall;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class WallRepository extends ServiceEntityRepository
{
    use FilterTrait;
    use DeactivatableTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Wall::class);
    }
}