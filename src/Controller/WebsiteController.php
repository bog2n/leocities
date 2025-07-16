<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class WebsiteController extends AbstractController
{
    #[Route('/~{user}/{slug<.*>}', name: 'app_website')]
    public function index(string $user, string $slug): Response
    {
        return new Response($user."'s website! path: ".$slug);
    }
}
