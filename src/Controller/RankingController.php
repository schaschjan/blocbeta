<?php

namespace App\Controller;

use App\Command\IndexCurrentCommand;
use App\Factory\RedisConnectionFactory;
use App\Repository\BoulderRepository;
use App\Scoring\DefaultScoring;
use App\Service\ContextService;
use App\Struct\BoulderStruct;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

/**
 * @Route("/ranking")
 */
class RankingController extends AbstractController
{
    private ContextService $contextService;
    private BoulderRepository $boulderRepository;
    private \Redis $redis;

    public function __construct(
        ContextService $contextService,
        BoulderRepository $boulderRepository
    )
    {
        $this->contextService = $contextService;
        $this->boulderRepository = $boulderRepository;
        $this->redis = RedisConnectionFactory::create();
    }

    /**
     * @Route("/current", methods={"GET"})
     */
    public function current()
    {
        $cacheKey = IndexCurrentCommand::getCacheKey($this->contextService->getLocation()->getId());

        if (!$this->redis->exists($cacheKey)) {
            $data = [];
        } else {
            $data = json_decode($this->redis->get($cacheKey));
        }

        return $this->json($data);
    }

    /**
     * @Route("/all-time", methods={"GET"})
     */
    public function allTime()
    {
        $ranking = $this->redis->get($this->contextService->getLocation()->getId() . "-all-time-ranking");

        return $this->json(json_decode($ranking, true));
    }
}