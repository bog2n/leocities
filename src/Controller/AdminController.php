<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Repository\UserRepository;
use App\Repository\ExtentRepository;

final class AdminController extends AbstractController
{
    #[Route('/admin/', name: 'app_admin')]
    public function index(UserRepository $user_repository, ExtentRepository $extent_repository): Response
    {
        $conn = $user_repository->getEntityManager()->getConnection();
        $users = $conn->fetchAllAssociative('SELECT
            id, username, quota_used, quota_limit
            FROM "user";');
    
        $freeBytes = $extent_repository->getFreeExtent();

        return $this->render('admin/index.html.twig', [
            'users' => $users,
            'free_bytes' => $freeBytes->getLength(),
        ]);
    }
}
