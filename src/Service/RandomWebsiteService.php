<?php

namespace App\Service;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use App\Repository\WebsiteRepository;
use App\Entity\Website;

/**
 * Used for retrieving random website list for landing page.
 *
 * Caches entries for specified amount of time
 */
class RandomWebsiteService
{
    const WEBSITE_CACHE_EXPIRY_TIME = 10;

    public function __construct(
        private CacheInterface $cache,
        private WebsiteRepository $repository,
    ) {}

    /**
     * Returns at most $max amount of websites in random order
     *
     * @param int $max amount of websites to retrieve
     *
     * @return Website[]
     */
    public function getWebsites(int $max): mixed
    {
        return $this->cache->get('randomWebsites', $this->getCacheCallback($max));
    }

    /**
     * Return cache callback function for retrieving at most $max amount of websites
     *
     * @param int $max amount of websites to retrieve
     *
     * @return function(ItemInterface $item)
     */
    private function getCacheCallback(int $max): mixed
    {
        return function(ItemInterface $item) {
            $item->expiresAfter(self::WEBSITE_CACHE_EXPIRY_TIME);
            return $this->repository->getRandomWebsites($max);
        };
    }
}

