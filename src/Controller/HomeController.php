<?php

namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    #[Route('/admin', name: 'app_admin')]
    public function admin(): Response
    {
        return $this->render('home/blank.html.twig', [
            'usemenu' => true,
            'usesidebar' => true,
        ]);
    }

    #[Route('/', name: 'app_home')]
    public function home(?int $id = null): Response
    {
        return $this->redirectToRoute('app_project');
    }
}
