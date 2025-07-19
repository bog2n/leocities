<?php

namespace App\Service\Fs;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception as HttpException;
use Symfony\Component\Filesystem\Path;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\DBAL\Exception as DBException;
use App\Entity\User;
use App\Entity\Inode;
use App\Entity\Dir;
use App\Repository\DirRepository;
use App\Repository\InodeRepository;
use App\Repository\UserRepository;
use App\Service\Fs\Exception;
use App\Service\Fs\Allocator;
use App\Service\Fs\Quota;

/**
 * Implements all the necessary filesystem methods for creating, deleting and
 * renaming files and directories.
 *
 * All methods except for get_file require authentication
 */
class FileService {
    public ?Inode $root_inode = null;

    public function __construct(
        private EntityManagerInterface $manager,
        private Security $security,
        private UserRepository $user_repository,
        private DirRepository $dir_repository,
        private InodeRepository $inode_repository,
        private Allocator $allocator,
        private Quota $quota,
        private string $block_file
    ) {
        $this->user = $security->getUser();
        if ($this->user !== null) {
            $this->setUp($this->user);
        }
    }

	/**
	 * Creates directory in specified parent directory
	 *
	 * @param int|Inode $parent_id target directory
	 * @param string    $name      director name
	 *
	 * @return Dir created directory
	 *
	 * @throws Exception\DirectoryAlreadyExists    if directory or file already exists
	 */
    public function mkdir(int|Inode $parent_id, string $name): Dir
    {
        if ($this->root_inode === null) {
            throw new HttpException\AccessDeniedHttpException;
        }

        $parentInode = $this->inode_repository->findOneBy([
            "id" => $parent_id,
            "owner" => $this->user->getId(),
        ]);
        if ($parentInode === null) {
            throw new HttpException\NotFoundHttpException;
        }
        $parent = $parentInode->getDir();
        if ($parent === null) {
            throw new Exception\DirectoryAlreadyExists; // it's a file but we don't care
        }

        $new = new Inode($this->user);
        $new->setName($name);
        $this->manager->persist($new);

        $parent->addChild($new);
        $this->manager->persist($parent);

        $newDir = new Dir();
        $newDir->setParent($new);
        $this->manager->persist($newDir);

        try {
            $this->manager->flush();
        } catch (\Exception $e) {
            if ($e instanceof DBException\UniqueConstraintViolationException) {
                throw new Exception\DirectoryAlreadyExists;
            } else {
                throw $e;
            }
        }

        return $newDir;
    }

	/**
	 * Renames specified file or directory
	 *
	 * @param int|Inode $inode_id target inode
	 * @param string    $name     desired filename
	 *
	 * @throws Exception\FileAlreadyExists when there is a file with the same filename
	 */
    public function rename(int|Inode $inode_id, string $name): void
    {
        if ($this->root_inode === null) {
            throw new HttpException\AccessDeniedHttpException;
        }

        $inode = $this->inode_repository->findOneBy([
            "id" => $inode_id,
            "owner" => $this->user->getId(),
        ]);
        if ($inode === null) {
            throw new HttpException\NotFoundHttpException;
        }

        $inode->setName($name);
		$inode->setLastModified(new \DateTime());
        $this->manager->persist($inode);
        try {
            $this->manager->flush();
        } catch (\Exception $e) {
            if ($e instanceof DBException\UniqueConstraintViolationException) {
                throw new Exception\FileAlreadyExists;
            } else {
                throw $e;
            }
        }

		return;
    }

	/**
	 * Deletes specified file or directory
	 *
	 * @param int|Inode $inode_id target inode
	 *
	 * @throws DirectoryNotEmpty when target inode is directory and it's not empty
	 */
    public function delete(int|Inode $inode_id): void
    {
        if ($this->root_inode === null) {
            throw new HttpException\AccessDeniedHttpException;
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
            $this->quota->removeBlocks(ceil($bytes_freed/BLOCK_SIZE));
        }

        $this->manager->remove($inode);
        $this->manager->flush();

		return;
    }

	/**
	 * Creates file in specified directory.
	 *
	 * @param int|Inode $parent_id target directory
	 * @param string    $filename  filename of new file
	 * @param string    $data      content of new file
	 *
	 * @throws Exception\FileAlreadyExists when there is already file with target filename
	 * @throws QuotaLimitExceeded when file content exceeds user quota
	 */
    public function create(int|Inode $parent_id, string $filename, string $data): void
    {
        if ($this->root_inode === null) {
            throw new HttpException\AccessDeniedHttpException;
        }

        $parentInode = $this->inode_repository->findOneBy([
            "id" => $parent_id,
            "owner" => $this->user->getId(),
        ]);
        if ($parentInode === null) {
            throw new HttpException\NotFoundHttpException;
        }

        $parentDir = $parentInode->getDir();
        if ($parentDir === null) {
            throw new HttpException\NotFoundHttpException;
        }

        $len = strlen($data);
        $this->quota->addBlocks(ceil($len/BLOCK_SIZE));
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

        $parentDir->addChild($inode);
        $this->manager->persist($parentDir);

        try {
            $this->manager->flush();
        } catch (\Exception $e) {
            if ($e instanceof DBException\UniqueConstraintViolationException) {
                throw new Exception\FileAlreadyExists;
            } else {
                throw $e;
            }
        }

        $this->manager->getConnection()->commit();

		return;
    }

	/**
	 * Returns content of specified file
	 *
	 * @param int|Inode $inode_id target inode
	 *
	 * @throws Exception\IsDirectoryException when target inode is a directory
	 */
    public function read($inode_id): string
    {
        if ($this->root_inode === null) {
            throw new HttpException\AccessDeniedHttpException;
        }

        $inode = $this->inode_repository->findOneBy([
            "id" => $inode_id,
            "owner" => $this->user->getId(),
        ]);
        if ($inode === null) {
            throw new HttpException\NotFoundHttpException;
        }
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

	/**
	 * Lists files in specified directory
	 *
	 * @param int|Inode $parent_id target directory
	 *
	 * @return array(
	 *               array(
	 *               int $inode id,
	 *               string $filename,
	 *               int $size (-1 for directory),
	 *               \DateTime $last_modified
	 *               )
	 *         )
	 *
	 * @throws Exception\IsFileException when target directory is a file
	 */
    public function listDir(int|Inode $inode_id): mixed
    {
        if ($this->root_inode === null) {
            throw new HttpException\AccessDeniedHttpException;
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
        if ($inode->getParent() !== null) {
			$root_dir = $inode->getParent()->getParent();
			$out[] = Array(
				$root_dir->getId(),
				'..',
				-1,
				$root_dir->getLastModified(),
			);
        }

        foreach ($dir->getChild() as $child) {
            $length = $this->inode_repository->getLength($child->getId());
			$out[] = Array(
				$child->getId(),
				$child->getName(),
				$length,
				$child->getLastModified(),
			);
        }

        return $out;
    }

	/**
	 * Resolves path and returns Inode object for that file or directory
	 *
	 * @param string $filepath path
	 */
    public function getInode(string $filepath): Inode
    {
        if ($this->root_inode === null) {
            throw new HttpException\AccessDeniedHttpException;
        }

        $filepath = Path::canonicalize($filepath);
        $paths = explode('/', $filepath);
        $current = $this->root_inode;
        if ($current === null) {
            throw new HttpException\NotFoundHttpException;
        }
        if ($filepath === "") {
            return $current;
        }

        for ($i = 0; $i < count($paths); $i++) {
            $ok = false;
            foreach ($current->getDir()->getChild() as $child) {
                if ($child->getName() === $paths[$i]) {
                    $current = $child;
                    $ok = true;
                    break;
                }
            }

            if ($ok === false) {
                throw new HttpException\NotFoundHttpException;
            }
        }

        return $current;
    }

	/**
	 * Returns file content for given user and path
	 *
	 * @param string $username username
	 * @param string $filepath path to file
	 *
	 * @throws Exception\IsDirectoryException when target fath is a directory
	 *         and index.html couldn't be found in it
	 */
    public function getFile(string $username, string $filepath): string
    {
        $this->user = $this->user_repository->findOneByUsername($username);
        if ($this->user === null) {
            throw new HttpException\NotFoundHttpException;
        }
        $this->setUp($this->user);

        $file = $this->getInode($filepath);
        if ($file->isDir()) {
            // try to get index.html
            if (str_ends_with($filepath, '/') || $filepath === '') {
                $file = $this->getInode($filepath.'index.html');
            } else {
                throw new Exception\IsDirectoryException;
            }
        }
        return $this->read($file);
    }

	/**
	 * Returns full filepath for given inode
	 *
	 * @param int|Inode $inode target inode
	 */
	public function getFilepath(int|Inode $inode): string
	{
        if ($this->root_inode === null) {
            throw new HttpException\AccessDeniedHttpException;
        }

		$path = '';
		$inode = $this->inode_repository->findOneById($inode);

		while ($inode->getId() !== $this->root_inode->getId()) {
			$path = $inode->getName().'/'.$path;
			$inode = $inode->getParent()->getParent();
		}

		return $path;
	}

	/**
	 * Initializes root_node of FileService for specified user.
	 *
	 * @param User $user user for which to initialize FileService
	 */
    private function setUp(User $user): void {
        $this->root_inode = $user->getRootInode();

        // initialize root directory
        if ($this->root_inode === null) {
            $this->root_inode = new Inode($this->user);
            $this->root_inode->setName('/');

            $this->user->setRootInode($this->root_inode);
            $this->manager->persist($this->user);

            $root = new Dir();
            $root->setParent($this->root_inode);

            $this->manager->persist($root);
            $this->manager->flush();
        }

		return;
    }
}

