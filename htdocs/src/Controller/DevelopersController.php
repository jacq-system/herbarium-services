<?php declare(strict_types=1);

namespace App\Controller;

use App\Service\DevelopersService;
use App\Service\DjatokaService;
use Doctrine\ORM\EntityNotFoundException;
use JACQ\Service\StatisticsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;

class DevelopersController extends AbstractController
{

    public function __construct(protected DevelopersService $developersService, protected readonly DjatokaService $djatokaService, protected readonly StatisticsService $statisticsService)
    {
    }

    #[Route('/tools/checkDjatokaServers', name: 'app_tools_checkDjatokaServers', defaults: ['sourceId' => null])]
    public function checkDjatokaServers(#[MapQueryParameter] ?int $sourceId): Response
    {
        try {
            $data = $this->djatokaService->getData($sourceId);
        } catch (EntityNotFoundException $exception) {
            $noRowError = $exception->getMessage();
            return $this->render('tools/djatokaCheck.html.twig', ["noRowError" => $noRowError]);
        }
        $warn = $data['warn'] ?? null;
        $ok = $data['ok'] ?? null;
        $fail = $data['fail'] ?? null;
        $noPicture = $data['noPicture'] ?? null;

        return $this->render('tools/djatokaCheck.html.twig', ["warn" => $warn, "ok" => $ok, "fail" => $fail, "noPicture" => $noPicture]);
    }

    #[Route('/tools/rest', name: 'tools_rest')]
    public function indexToolsRest(): Response
    {
        $data = $this->developersService->testApiWithExamples();
        return $this->render('tools/rest.html.twig', ["results" => $data]);
    }

}
