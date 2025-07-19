<?php

namespace App\Service\Fs;

use Symfony\Bundle\SecurityBundle\Security;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Quota class, responsible for accounting filesystem usage of users.
 */
class Quota {
    public function __construct(
        private EntityManagerInterface $manager,
        private Security $security
    ) {
    }

    /**
     * Adds specified amount of blocks to authenticated user
     *
     * @param int $num amount of blocks
     *
     * @return number of blocks used by user after operation
     */
    public function addBlocks(int $num): int
    {
        $user = $this->security->getUser();
        if ($user === null) {
            throw new \Exception("user not found");
        }

        $conn = $this->manager->getConnection();

        try {
            $new_quota = $conn->fetchNumeric('UPDATE "user" u
                SET quota_used = quota_used + ?
                WHERE id = ?
                RETURNING quota_used', [
                    1 => $num,
                    2 => $user->getId(),
                ]);
        } catch (\Exception $e) {
            // exception thrown, probably quota check failed
            throw new Exception\QuotaLimitExceeded;
        }

        return $new_quota[0];
    }

    /**
     * Removes specified amount of blocks from authenticated user
     *
     * @param int $num amount of blocks
     *
     * @return number of blocks used by user after operation
     */
    public function removeBlocks(int $num): int
    {
        $user = $this->security->getUser();
        if ($user === null) {
            throw new \Exception("user not found");
        }

        $conn = $this->manager->getConnection();

        try {
            $new_quota = $conn->fetchNumeric('UPDATE "user" u
                SET quota_used = quota_used - ?
                WHERE id = ?
                RETURNING quota_used', [
                    1 => $num,
                    2 => $user->getId(),
                ]);
        } catch (\Exception $e) {
            // exception thrown, probably freed too much space
            $conn->fetchNumeric('UPDATE "user" u
                SET quota_used = 0
                WHERE id = ?', [
                    1 => $user->getId(),
                ]);
            return 0;
        }

        return $new_quota[0];
    }
}

