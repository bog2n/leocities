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
use App\Service\Fs\FileService;
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
		]);
	}
}
