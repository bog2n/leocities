<?php

namespace App\Service;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use App\Repository\WebsiteRepository;

class RandomWebsiteService
{
    const MAX_WEBSITES = 10;
    const WEBSITE_CACHE_EXPIRY_TIME = 10;

    public function __construct(
        private CacheInterface $cache,
        private WebsiteRepository $repository,
    ) {}

    public function getWebsites(): mixed {
        return $this->cache->get('randomWebsites', $this->getCacheCallback());
    }

    private function getCacheCallback() {
        return function(ItemInterface $item) {
            $item->expiresAfter(self::WEBSITE_CACHE_EXPIRY_TIME);
            return $this->repository->getRandomWebsites(self::MAX_WEBSITES);
        };
    }
}

