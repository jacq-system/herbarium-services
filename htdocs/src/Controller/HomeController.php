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

    #[Route('/jacq-services/rest/openapi', name: 'swaggerJsonJacqPath')]
    public function openapiJson(): Response
    {
       return  $this->redirectToRoute('app.swagger');
    }

        #[Route('/jacq-services/rest/description', name: 'swaggerJacqPath')]
    public function openapiDescription(): Response
    {
        return  $this->redirectToRoute('app.swagger_ui');
    }
}
