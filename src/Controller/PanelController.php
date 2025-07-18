<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpKernel\Exception as HttpException;
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
    #[Route('/panel/', name: 'app_panel')]
    public function index(
        Request $request,
        EntityManagerInterface $manager,
        FileService $fs
    ): Response
    {
        $user = $this->getUser();
        $website = $user->getWebsite();

        $info_form = $this->createForm(DescriptionType::class, $website);
        $info_form->handleRequest($request);

        if ($info_form->isSubmitted() && $info_form->isValid()) {
            $manager->persist($website);
            $manager->flush();
        }

        return $this->render('panel/index.html.twig', [
            'user' => $user,
            'info_form' => $info_form,
            'root_inode' => $fs->root_inode->getId(),
        ]);
    }

    #[Route('/panel/file/{id}', methods: ['GET'], name: 'file_get')]
    public function file_get(
        FileService $fs,
        InodeRepository $repository,
        Request $request,
        int $id
    ): Response
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
                'files' => $fs->list_dir($inode),
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

    #[Route('/panel/file/{id}/mkdir', methods: ['POST'], name: 'file_mkdir')]
    public function file_mkdir(
        FileService $fs,
        InodeRepository $repository,
        Request $request,
        int $id
    ): Response
    {
        $name = $request->request->get('name');
        if ($name === null) {
            throw new HttpException\BadRequestException;
        }
        $fs->mkdir($id, $name);

        return $this->render('panel/list.html.twig', [
            'files' => $fs->list_dir($id),
            'root_inode' => $fs->root_inode->getId(),
            'is_root_dir' => $fs->root_inode->getId() == $id,
        ]);
    }

    #[Route('/panel/file/{id}', methods: ['DELETE'], name: 'file_delete')]
    public function file_delete(
        FileService $fs,
        InodeRepository $repository,
        Request $request,
        int $id,
    ): Response
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

        return $this->render('panel/list.html.twig', [
            'files' => $fs->list_dir($parent),
            'root_inode' => $parent,
            'is_root_dir' => $fs->root_inode->getId() == $parent,
        ]);
    }

    #[Route('/panel/file/{id}/new', methods: ['POST', 'GET'], name: 'file_create')]
    public function file_create(
        int $id,
        Request $request,
        FileService $fs
    ): Response
    {
        if ($request->getMethod() === "POST") {
            $upload_form = $this->createForm(UploadType::class);
            $upload_form->handleRequest($request);

            if ($upload_form->isSubmitted() && $upload_form->isValid()) {
                foreach ($upload_form['file']->getData() as $file) {
                    $fs->create(
                        $id,
                        $file->getClientoriginalName(),
                        $file->getContent()
                    );
                }

                return $this->render('panel/list.html.twig', [
                    'files' => $fs->list_dir($id),
                    'root_inode' => $id,
                    'is_root_dir' => $fs->root_inode->getId() == $id,
                ]);
            } else {
                throw new HttpException\BadRequestException;
            }
        } else {
            return $this->render('panel/new.html.twig', [
                'root_inode' => $id,
            ]);
        }
    }
}
