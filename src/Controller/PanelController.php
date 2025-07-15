<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Doctrine\ORM\EntityManagerInterface;
use App\Form\DescriptionType;

final class PanelController extends AbstractController
{
    #[Route('/panel/', name: 'app_panel')]
	public function index(
		Request $request,
		EntityManagerInterface $manager
	): Response
    {
		$website = $this->getUser()->getWebsite();
		$info_form = $this->createForm(DescriptionType::class, $website);

		$info_form->handleRequest($request);

		if ($info_form->isSubmitted() && $info_form->isValid()) {
			$manager->persist($website);
			$manager->flush();
		}

		return $this->render('panel/index.html.twig', [
			'user' => $this->getUser(),
			'info_form' => $info_form,
		]);
    }
}
