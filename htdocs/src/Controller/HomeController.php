<?php declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class
HomeController extends AbstractController
{

    #[Route('/version', name: 'version')]
    public function version(): Response
    {
        return $this->json($this->getParameter('app.version'));
    }
}
