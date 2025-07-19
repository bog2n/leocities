<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Mime\MimeTypes;
use App\Service\Fs\FileService;
use App\Service\Fs\Exception\IsDirectoryException;

final class WebsiteController extends AbstractController
{
    /**
     * Handles serving user files
     */
    #[Route('/~{user}/{slug<.*>}', name: 'app_website')]
    public function index(string $user, string $slug, FileService $fs): Response
    {
        $ext = Path::getExtension($slug);
        if ($slug === '' || str_ends_with($slug, '/')) {
            $ext = 'html';
        }

        $mimeTypes = new MimeTypes();
        $mime = $mimeTypes->getMimeTypes($ext);
        if (count($mime) === 0) {
            $mime = 'application/octet-stream';
        } else {
            $mime = $mime[0];
        }

        try {
            return new Response($fs->getFile($user, $slug), 200, [
                "Content-Type" => $mime,
            ]);
        } catch (IsDirectoryException $e) {
            return $this->redirectToRoute('app_website', [
                "user" => $user,
                "slug" => $slug.'/',
            ]);
        }
    }
}
