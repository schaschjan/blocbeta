<?php

namespace App\Controller;

use App\Repository\DeactivatableRepositoryInterface;
use App\Repository\FilterableRepositoryInterface;

trait FilterTrait
{
    /**
     * @throws \Exception
     */
    public function handleFilters($filters, $repository, int $locationId, \Closure $defaultQuery = null)
    {
        if ($repository instanceof DeactivatableRepositoryInterface) {
            if ($filters === "all") {
                return $repository->getAll($locationId);
            }

            if (!$filters) {
                return $repository->getActive($locationId);
            }
        }

        if (!$defaultQuery && $repository instanceof FilterableRepositoryInterface) {
            return $repository->queryWhere(
                $locationId,
                ["active" => "bool"],
                $filters
            );
        }

        return $defaultQuery($filters, $repository, $locationId);

    }
}