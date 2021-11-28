<?php

namespace App\Repository;

use App\Entity\Boulder;
use App\Entity\Grade;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\Persistence\ManagerRegistry;

class BoulderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Boulder::class);
    }

    public function countByLocation(int $locationId)
    {
        $result = $this->createQueryBuilder('boulder')
            ->select("count(boulder.id)")
            ->where("boulder.location = :location")
            ->setParameter("location", $locationId)
            ->getQuery()
            ->getSingleResult();

        return $result ? $result[1] : 0;
    }

    public function getOne(int $id): ?Boulder
    {
        return $this->createQueryBuilder("boulder")
            ->innerJoin("boulder.holdType", "holdType")
            ->innerJoin("boulder.grade", "grade")
            ->innerJoin("boulder.internalGrade", "internalGrade")
            ->innerJoin("boulder.startWall", "startWall")
            ->leftJoin("boulder.endWall", "endWall")
            ->leftJoin("boulder.setters", "setter")
            ->leftJoin("boulder.tags", "tag")
            ->leftJoin("boulder.comments", "comments")
            ->leftJoin("boulder.ratings", "rating")
            ->where("boulder.id = :id")
            ->setParameter("id", $id)
            ->getQuery()
            ->setFetchMode(Grade::class, "grade", ClassMetadataInfo::FETCH_EAGER)
            ->getOneOrNullResult();
    }

    public function countByStatus(int $locationId, string $status = Boulder::STATUS_ACTIVE): int
    {
        $result = $this->createQueryBuilder('boulder')
            ->select("count(boulder.id)")
            ->where("boulder.location = :location")
            ->andWhere("boulder.status = :status")
            ->setParameter("location", $locationId)
            ->setParameter("status", $status)
            ->getQuery()
            ->getSingleResult();

        return $result ? $result[1] : 0;
    }

    public function getByStatus(int $locationId, string $status = Boulder::STATUS_ACTIVE): ?array
    {
        return $this->createQueryBuilder("boulder")
            ->leftJoin("boulder.setters", "setter")
            ->leftJoin("boulder.startWall", "startWall")
            ->leftJoin("boulder.endWall", "endWall")
            ->leftJoin("boulder.internalGrade", "internalGrade")
            ->innerJoin("boulder.grade", "grade")
            ->innerJoin("boulder.holdType", "holdType")
            ->innerJoin("startWall.areas", "area")
            ->where("boulder.location = :location")
            ->andWhere("boulder.status = :status")
            ->setParameter("location", $locationId)
            ->setParameter("status", $status)
            ->getQuery()
            ->getResult();
    }

    public function getWithAscents(string $locationId, string $status = Boulder::STATUS_ACTIVE): array
    {
        return $this->createQueryBuilder('boulder')
            ->select("boulder", "ascent", "user")
            ->innerJoin('boulder.ascents', 'ascent')
            ->innerJoin('ascent.user', 'user')
            ->where('boulder.location = :location')
            ->andWhere('boulder.status = :status')
            ->andWhere('user.visible = :visible')
            ->setParameter('location', $locationId)
            ->setParameter('status', $status)
            ->setParameter('visible', true)
            ->getQuery()
            ->getResult();
    }
}
