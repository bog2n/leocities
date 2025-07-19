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
    public function __construct(
        private CacheInterface $cache,
        private WebsiteRepository $repository,
        private int $cache_expiry_time = 30,
        private int $website_num = 10,
    ) {}

    /**
     * Returns at most $max amount of websites in random order
     *
     * @return Website[]
     */
    public function getWebsites(): mixed
    {
        return $this->cache->get('randomWebsites', $this->getCacheCallback());
    }

    /**
     * Returns expiration time for random websites
     */
    public function getCacheExpiryTime(): int {
        return $this->cache_expiry_time;
    }

    /**
     * Returns amount of random websites that are being fetched
     */
    public function getWebsiteNum(): int {
        return $this->website_num;
    }

    /**
     * Return cache callback function for retrieving at most $max amount of websites
     *
     * @return function(ItemInterface $item)
     */
    private function getCacheCallback(): mixed
    {
        return function(ItemInterface $item) {
            $item->expiresAfter($this->cache_expiry_time);
            return $this->repository->getRandomWebsites($this->website_num);
        };
    }
}

