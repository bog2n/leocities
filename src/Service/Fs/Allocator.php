<?php

namespace App\Service\Fs;

use App\Repository\ExtentRepository;

class Allocator {
    const BLOCK_SIZE = 512;

    public function __construct(private ExtentRepository $repository) {
    }

    public function alloc(int $length) {
        $em = $this->repository->getEntityManager();
        $conn = $em->getConnection();
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
        $em->persist($free);
        $em->flush($free);

        $conn->commit();

        return $free;
    }

    public function free($inode) {
        throw new \Exception("not implemented");
    }
}

