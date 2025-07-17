<?php

namespace App\Service\Fs;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception as HttpException;
use Symfony\Component\Filesystem\Path;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\DBAL\Exception as DBException;
use App\Entity\Inode;
use App\Entity\Dir;
use App\Repository\DirRepository;
use App\Repository\InodeRepository;
use App\Service\Fs\Exception;
use App\Service\Fs\Allocator;
use App\Service\Fs\Quota;

class FileService {
    private ?Inode $root_inode = null;

    public function __construct(
        private EntityManagerInterface $manager,
        private Security $security,
        private DirRepository $dir_repository,
        private InodeRepository $inode_repository,
        private Allocator $allocator,
        private Quota $quota,
        private $block_file
    ) {
        $this->user = $security->getUser();
        if ($this->user !== null) {
            $this->root_inode = $this->user->getRootInode();

            // initialize root directory
            if ($this->root_inode === null) {
                $this->root_inode = new Inode($this->user);
                $this->root_inode->setName('/');
                $manager->persist($this->root_inode);

                $this->user->setRootInode($this->root_inode);
                $manager->persist($this->user);

                $root = new Dir();
                $root->setParent($this->root_inode);
                $manager->persist($root);
                $manager->flush();
            }
        }
    }

    public function mkdir($parent_id, $name)
    {
        if ($this->root_inode === null) {
            throw new HttpException\UnauthorizedHttpException;
        }

        $parent_inode = $this->inode_repository->findOneBy([
            "id" => $parent_id,
            "owner" => $this->user->getId(),
        ]);
        if ($parent_inode === null) {
            throw new HttpException\NotFoundHttpException;
        }
        $parent = $parent_inode->getDir();
        if ($parent === null) {
            throw new Exception\DirectoryAlreadyExists; // it's a file but we don't care
        }

        $new = new Inode($this->user);
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

    public function rename($inode_id, $name)
    {
        if ($this->root_inode === null) {
            throw new HttpException\UnauthorizedHttpException;
        }

        $inode = $this->inode_repository->findOneBy([
            "id" => $inode_id,
            "owner" => $this->user->getId(),
        ]);
        if ($inode === null) {
            throw new HttpException\NotFoundHttpException;
        }

        $inode->setName($name);
        $this->manager->persist($inode);
        $this->manager->flush();
    }

    public function delete($inode_id)
    {
        if ($this->root_inode === null) {
            throw new HttpException\UnauthorizedHttpException;
        }

        $inode = $this->inode_repository->findOneBy([
            "id" => $inode_id,
            "owner" => $this->user->getId(),
        ]);
        if ($inode === null) {
            throw new HttpException\NotFoundHttpException;
        }

        $dir = $inode->getDir();
        if ($dir !== null) {
            if (count($dir->getChild()) !== 0) {
                throw new Exception\DirectoryNotEmpty;
            }
            $this->manager->remove($dir);
        } else {
            $bytes_freed = $this->allocator->free($inode);
            $this->quota->remove_blocks(ceil($bytes_freed/BLOCK_SIZE));
        }

        $this->manager->remove($inode);
        $this->manager->flush();
    }

    public function create($parent_id, $filename, $data)
    {
        if ($this->root_inode === null) {
            throw new HttpException\UnauthorizedHttpException;
        }

        $parent_inode = $this->inode_repository->findOneBy([
            "id" => $parent_id,
            "owner" => $this->user->getId(),
        ]);
        if ($parent_inode === null) {
            throw new HttpException\NotFoundHttpException;
        }

        $parent_dir = $parent_inode->getDir();
        if ($parent_dir === null) {
            throw new HttpException\NotFoundHttpException;
        }

        $len = strlen($data);
        $this->quota->add_blocks(ceil($len/BLOCK_SIZE));
        $extent = $this->allocator->alloc($len);

        $handle = fopen($this->block_file, "c");
        if (!$handle) {
            throw new \Exception("Can't open block file");
        }
        if (fseek($handle, $extent->getStart() * BLOCK_SIZE, SEEK_SET) === -1) {
            throw new \Exception("Can't seek in block file");
        }
        if (!fwrite($handle, $data)) {
            throw new \Exception("Can't write to block file");
        }
        if (!fclose($handle)) {
            throw new \Exception("Can't close block file");
        }

        $inode = new Inode($this->user);
        $inode->addExtent($extent);
        $inode->setName($filename);
        $this->manager->persist($inode);

        $parent_dir->addChild($inode);
        $this->manager->persist($parent_dir);
        $this->manager->flush();

        $this->manager->getConnection()->commit();
    }

    public function read($inode_id)
    {
        if ($this->root_inode === null) {
            throw new HttpException\UnauthorizedHttpException;
        }

        $inode = $this->inode_repository->findOneBy([
            "id" => $inode_id,
            "owner" => $this->user->getId(),
        ]);
        if ($inode->isDir()) {
            throw new Exception\IsDirectoryException;
        }

        $out = '';

        $handle = fopen($this->block_file, "r");
        if (!$handle) {
            throw new \Exception("Can't open block file");
        }
        
        foreach ($inode->getExtent() as $extent) {
            if (fseek($handle, $extent->getStart() * BLOCK_SIZE, SEEK_SET) === -1) {
                throw new \Exception("Can't seek in block file");
            }
            $chunk = fread($handle, $extent->getLength());
            if ($chunk === false) {
                throw new \Exception("Can't read block file");
            }
            $out .= $chunk;
        }

        if (!fclose($handle)) {
            throw new \Exception("Can't close block file");
        }

        return $out;
    }

    // returns array of Array('name' => length) entries,
    // length is set to -1 if entry is a directory
    public function list_dir($inode_id)
    {
        if ($this->root_inode === null) {
            throw new HttpException\UnauthorizedHttpException;
        }

        $inode = $this->inode_repository->findOneBy([
            "id" => $inode_id,
            "owner" => $this->user->getId(),
        ]);
        $dir = $inode->getDir();
        if ($dir === null) {
            throw new Exception\IsFileException;
        }

        $out = Array();

        foreach ($dir->getChild() as $child) {
            $length = $this->inode_repository->getLength($child->getId());
            $out[] = Array($child->getName(), $length);
        }

        return $out;
    }

    public function get_inode($filepath)
    {
        if ($this->root_inode === null) {
            throw new HttpException\UnauthorizedHttpException;
        }

        $filepath = Path::canonicalize($filepath);
        $paths = explode('/', $filepath);
        $current = $this->root_inode->getDir();

        // traverse directories
        // TODO: caching this would be good idea
        for ($i = 0; $i < count($paths)-1; $i++) {
            $ok = null;
            foreach ($current->getChild() as $el) {
                if ($el->getName() == $paths[$i]) {
                    $ok = $el->getDir();
                    break;
                }
            }
            if ($ok === null) {
                throw new HttpException\NotFoundHttpException;
            }
            $current = $ok;
        }

        $ok = null;
        foreach ($current->getChild() as $el) {
            if ($el->getName() == $paths[$i]) {
                $ok = $el;
                break;
            }
        }
        if ($ok === null) {
            throw new HttpException\NotFoundHttpException;
        }
        return $el;
    }
}

