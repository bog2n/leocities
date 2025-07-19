<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Exception as HttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Mime\MimeTypes;
use Doctrine\ORM\EntityManagerInterface;
use App\Form\DescriptionType;
use App\Form\UploadType;
use App\Service\Fs\FileService;
use App\Service\Fs\Exception;
use App\Repository\InodeRepository;

final class PanelController extends AbstractController
{
    /**
     * Renders main page in user panel
     */
    #[Route('/panel/', name: 'app_panel')]
    public function index(Request $request, EntityManagerInterface $manager, FileService $fs): Response
    {
        $user = $this->getUser();
        $website = $user->getWebsite();

        $infoForm = $this->createForm(DescriptionType::class, $website);
        $infoForm->handleRequest($request);

        if ($infoForm->isSubmitted() && $infoForm->isValid()) {
            $manager->persist($website);
            $manager->flush();
        }

        return $this->render('panel/index.html.twig', [
            'user' => $user,
            'info_form' => $infoForm,
            'root_inode' => $fs->root_inode->getId(),
        ]);
    }

    /**
     * Returns file contents if id is of a file, otherwise renders directory listing
     */
    #[Route('/panel/file/{id}', methods: ['GET'], name: 'file_get')]
    public function file_get(FileService $fs, InodeRepository $repository, Request $request, int $id): Response
    {
        $inode = $repository->findOneBy([
            "id" => $id,
            "owner" => $this->getUser(),
        ]);
        if ($inode === null) {
            throw new HttpException\NotFoundHttpException;
        }

        if ($inode->isDir()) {
            return $this->render('panel/list.html.twig', [
                'files' => $fs->listDir($inode),
                'root_inode' => $inode->getId(),
                'is_root_dir' => $fs->root_inode->getId() == $inode->getId(),
            ]);
        }

        $ext = Path::getExtension($inode->getName());
        $mimeTypes = new MimeTypes();
        $mime = $mimeTypes->getMimeTypes($ext);
        if (count($mime) === 0) {
            $mime = 'application/octet-stream';
        } else {
            $mime = $mime[0];
        }

        return new Response($fs->read($inode), 200, [
            "Content-Type" => $mime,
        ]);
    }

    /**
     * Creates directory in specified inode
     */
    #[Route('/panel/file/{id}/mkdir', methods: ['POST'], name: 'file_mkdir')]
    public function file_mkdir(FileService $fs, InodeRepository $repository, Request $request, int $id): Response
    {
        $name = $request->request->get('name');
        if ($name === null) {
            throw new HttpException\BadRequestException;
        }
        try {
            $fs->mkdir($id, $name);
        } catch (\Exception $e) {
            if ($e instanceof Exception\DirectoryAlreadyExists) {
                return new Response('Already exists', 409, []);
            }
            throw $e;
        }

        return $this->render('panel/list.html.twig', [
            'files' => $fs->listDir($id),
            'root_inode' => $id,
            'is_root_dir' => $fs->root_inode->getId() == $id,
        ]);
    }

    /**
     * Deletes specifies file.
     *
     * throws error if directory is full.
     * on success renders updated file listing
     */
    #[Route('/panel/file/{id}', methods: ['DELETE'], name: 'file_delete')]
    public function file_delete(FileService $fs, InodeRepository $repository, Request $request, int $id,): Response
    {
        $file = $repository->findOneBy([
            "id" => $id,
            "owner" => $this->getUser(),
        ]);

        $parent = $file->getParent()->getParent()->getId();
        try {
            $fs->delete($id);
        } catch (Exception\DirectoryNotEmpty) {
            return new Response("Directory not empty", 406);
        }

        $response = $this->render('panel/list.html.twig', [
            'files' => $fs->listDir($parent),
            'root_inode' => $parent,
            'is_root_dir' => $fs->root_inode->getId() == $parent,
        ]);
        $response->headers->set('HX-Trigger', 'quota-update');
        return $response;
    }

    /**
     * Handles file uploads and their creation.
     *
     * on success renders file listing
     */
    #[Route('/panel/file/{id}/new', methods: ['POST', 'GET'], name: 'file_create')]
    public function file_create(int $id, Request $request, FileService $fs): Response
    {
        $uploadForm = $this->createForm(UploadType::class);
        $uploadForm->handleRequest($request);

        if ($request->getMethod() === "POST" && $uploadForm->isSubmitted()) {
            if ($uploadForm->isValid()) {
                $ok = true;
                $errors = '';

                foreach ($uploadForm['file']->getData() as $file) {
                    try {
                        $fs->create(
                            $id,
                            $file->getClientoriginalName(),
                            $file->getContent()
                        );
                    } catch (\Exception $e) {
                        $ok = false;
                        if ($e instanceof Exception\FileAlreadyExists) {
                            $errors .= $e->getMessage().'<br>';
                        } elseif ($e instanceof Exception\QuotaLimitExceeded) {
                            $errors .= $e->getMessage().'<br>';
                        } else {
                            throw $e;
                        }
                    }
                }

                if (!$ok) {
                    return new Response($errors, 400);
                }

                $response = $this->render('panel/list.html.twig', [
                    'files' => $fs->listDir($id),
                    'root_inode' => $id,
                    'is_root_dir' => $fs->root_inode->getId() == $id,
                ]);
                $response->headers->set('HX-Trigger', 'quota-update');
                return $response;
            } else {
                return new Response("Can't upload file", 400);
            }
        } else {
            return $this->render('panel/new.html.twig', [
                'root_inode' => $id,
            ]);
        }
    }

    /*
     * Handles file renaming.
     *
     * on success renders updated file listing
     */
    #[Route('/panel/file/{id}', methods: ['POST'], name: 'file_update')]
    public function file_update(int $id, Request $request, InodeRepository $repository, FileService $fs): Response
    {
        $name = $request->request->get('name');
        if ($name === "") {
            return new Response("Name can't be null", 400);
        }

        $file = $repository->findOneBy([
            "id" => $id,
            "owner" => $this->getUser(),
        ]);

        try {
            $fs->rename($id, $name);
        } catch (\Exception $e) {
            if ($e instanceof Exception\FileAlreadyExists) {
                return new Response('Already exists', 409, []);
            } else {
                throw $e;
            }
        }

        $parent = $file->getParent()->getParent()->getId();
        return $this->render('panel/list.html.twig', [
            'files' => $fs->listDir($parent),
            'root_inode' => $parent,
            'is_root_dir' => $fs->root_inode->getId() == $parent,
        ]);
    }

    /**
     * Returns formatted string of user quota
     */
    #[Route('/panel/quota', methods: ['GET'], name: 'get_quota')]
    public function get_quota(): Response
    {
        return $this->render('panel/quota.html.twig', [
            'quota_used' => $this->getUser()->getQuotaUsed() * 512,
            'quota_limit' => $this->getUser()->getQuotaLimit() * 512,
        ]);
    }

    /**
     * Returns full path of specified file or directory
     */
    #[Route('/panel/filepath/{id}', methods: ['GET'], name: 'get_filepath')]
    public function get_filepath(int $id, FileService $fs): Response
    {
        $filepath = '/'.$fs->getFilepath($id);

        return new Response($filepath);
    }
}
