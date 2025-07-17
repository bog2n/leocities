<?php

namespace App\Service\Fs;

define('App\Service\Fs\BLOCK_SIZE', 512);

use App\Repository\ExtentRepository;

class Allocator {
    public function __construct(private ExtentRepository $repository)
    {
        $this->em = $this->repository->getEntityManager();
    }

    // tries to find allocate extents in block_file,
    // caller must commit transaction itself
    //
    // returns allocated extent
    public function alloc(int $length)
    {
        $conn = $this->em->getConnection();
        $conn->beginTransaction();

        // acquire exclusive lock on extents
        // TODO: possible performance bottleneck here
        $conn->executeQuery('LOCK TABLE extent');

        // lock db
        $free = $this->repository->getFreeExtent();

        if ($free->getLength() < $length) {
            throw new Exception\NotEnoughSpaceException;
        }

        $free->setLength($length);
        $this->em->persist($free);
        $this->em->flush($free);

        return $free;
    }

    // frees extents owned by inode,
    // caller must commit transaction itself
    //
    // returns number of bytes freed
    public function free($inode)
    {
        $len = 0;
        foreach ($inode->getExtent() as $extent) {
            $len += $extent->getLength();
            $this->em->remove($extent);
        }
        return $len;
    }
}

