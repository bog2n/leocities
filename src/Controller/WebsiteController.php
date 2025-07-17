<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Mime\MimeTypes;
use App\Service\Fs\FileService;

final class WebsiteController extends AbstractController
{
    #[Route('/~{user}/{slug<.*>}', name: 'app_website')]
    public function index(string $user, string $slug, FileService $fs): Response
    {
        $ext = Path::getExtension($slug);

        $mimeTypes = new MimeTypes();
        $mime = $mimeTypes->getMimeTypes($ext);
        if (count($mime) === 0) {
            $mime = 'application/octet-stream';
        } else {
            $mime = $mime[0];
        }

        return new Response($fs->get_file($user, $slug), 200, [
            "Content-Type" => $mime,
        ]);
    }
}
