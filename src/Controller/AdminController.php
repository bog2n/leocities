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

        $files = $conn->fetchAllAssociative('
            WITH files AS (
                SELECT inode_id, sum(length) AS sz
                FROM extent GROUP BY id
            ), users AS (
                SELECT u.username, i.id, i.name AS filename
                FROM inode i
                JOIN "user" u
                ON u.id = i.owner_id
            )
            SELECT f.inode_id as id, u.filename, f.sz AS size, u.username AS owner
            FROM files f
            JOIN users u
            ON f.inode_id = u.id');


        return $this->render('admin/index.html.twig', [
            'users' => $users,
            'free_bytes' => $freeBytes->getLength(),
            'files' => $files,
        ]);
    }
}
