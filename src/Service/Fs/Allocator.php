<?php

namespace App\Service\Fs;

use App\Repository\ExtentRepository;
use App\Entity\Extent;
use App\Entity\Inode;

/**
 * Filesystem allocator, frees and allocates extents in block file.
 *
 * Currently it's very simple, just searches for last free space in block file
 * and allocates extent of given lenght there. This might be an issue when block
 * file gets fragmented and there is technically enough free space in it but
 * allocated block at the end effectively makes it unusable.
 */
class Allocator {
    public function __construct(private ExtentRepository $repository)
    {
        $this->em = $this->repository->getEntityManager();
    }

    /**
     * Tries to find and allocate extents in block file, caller must commit
     * database transaction itself
     *
     * @param int $lenght length of space to allocate
     *
     * @return Extent allocated extent
     *
     * @throws Exception\NotEnoughSpaceException when there is not enough space in block file
     */
    public function alloc(int $length): Extent
    {
        $conn = $this->em->getConnection();
        $conn->beginTransaction();

        // acquire exclusive lock on extents
        // TODO: possible performance bottleneck here
        $conn->executeQuery('LOCK TABLE extent');

        $free = $this->repository->getFreeExtent();

        if ($free->getLength() < $length) {
            throw new Exception\NotEnoughSpaceException;
        }

        $free->setLength($length);
        $this->em->persist($free);
        $this->em->flush($free);

        return $free;
    }

    /**
     * Frees extents owned by inode,
     * caller must commit transaction itself
     *
     * @param Inode $inode inode to free
     *
     * @return int number of bytes freed
     */
    public function free(Inode $inode): int
    {
        $len = 0;
        foreach ($inode->getExtent() as $extent) {
            $len += $extent->getLength();
            $this->em->remove($extent);
        }
        return $len;
    }
}

