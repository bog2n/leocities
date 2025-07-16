<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Service\RandomWebsiteService;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(RandomWebsiteService $randomWebsite): Response
    {
        return $this->render('home/index.html.twig', [
            'websites' => $randomWebsite->getWebsites(),
        ]);
    }
}
