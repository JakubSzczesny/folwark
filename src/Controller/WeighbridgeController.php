<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class WeighbridgeController extends AbstractController
{
    #[Route('/waga', name: 'app_weighbridge', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('weighbridge/index.html.twig');
    }
}
