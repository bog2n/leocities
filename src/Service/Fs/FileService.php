<?php

namespace App\Service\Fs;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception as HttpException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\DBAL\Exception as DBException;
use App\Entity\Inode;
use App\Entity\Dir;
use App\Repository\DirRepository;
use App\Repository\InodeRepository;
use App\Service\Fs\Exception;

class FileService {
    private ?Inode $root_inode = null;

    public function __construct(
        private EntityManagerInterface $manager,
        private Security $security,
        private DirRepository $dir_repository,
        private InodeRepository $inode_repository
    ) {
        $user = $security->getUser();
        if ($user !== null) {
            $this->root_inode = $user->getRootInode();

            // initialize root directory
            if ($this->root_inode === null) {
                $this->root_inode = new Inode();
                $this->root_inode->setName('/');
                $manager->persist($this->root_inode);

                $user->setRootInode($this->root_inode);
                $manager->persist($user);

                $root = new Dir();
                $root->setParent($this->root_inode);
                $manager->persist($root);
                $manager->flush();
            }
        }
    }

    public function mkdir($parent_id, $name) {
        if ($this->root_inode === null) {
            throw new HttpException\UnauthorizedHttpException;
        }

        $parent_inode = $this->inode_repository->findOneById($parent_id);
        if ($parent_inode === null) {
            throw new HttpException\NotFoundHttpException;
        }
        $parent = $parent_inode->getDir();
        if ($parent === null) {
            throw new DirectoryAlreadyExists; // it's a file but we don't care
        }

        $new = new Inode();
        $new->setName($name);
        $this->manager->persist($new);

        $new_dir = new Dir();
        $new_dir->setParent($new);
        $this->manager->persist($new_dir);

        $parent->addChild($new);
        $this->manager->persist($parent);

        try {
            $this->manager->flush();
        } catch (\Exception $e) {
            if ($e instanceof DBException\UniqueConstraintViolationException) {
                throw new Exception\DirectoryAlreadyExists;
            } else {
                throw $e;
            }
        }

        return $new_dir;
    }
}

