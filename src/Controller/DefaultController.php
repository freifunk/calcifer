<?php
namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class DefaultController extends AbstractController
{
    #[Route('/hello/{name}')]
    public function hello(string $name): Response
    {
        return $this->render('default/name.html.twig', [
            'name' => $name,
        ]);
    }

    #[Route('/über', name: 'about_calcifer')]
    public function indexAction(): Response
    {
        return $this->render('default/index.html.twig');
    }
}