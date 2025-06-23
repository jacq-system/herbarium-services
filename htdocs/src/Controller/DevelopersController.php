<?php declare(strict_types=1);

namespace App\Controller;

use App\Service\DevelopersService;
use App\Service\DjatokaService;
use Doctrine\ORM\EntityNotFoundException;
use JACQ\Service\StatisticsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class DevelopersController extends AbstractController
{

    public function __construct(protected DevelopersService $developersService, protected readonly DjatokaService $djatokaService, protected readonly StatisticsService $statisticsService, protected HttpClientInterface $client)
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
        return new StreamedResponse(function () {
            ob_implicit_flush(true);
            echo '<html><head><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous"></head><body><div class="container-fluid">';
            $responseSwagger = $this->client->request('GET', 'https://jacqservicestest.dyn.cloud.e-infra.cz/doc.json');
            $apiDoc = json_decode($responseSwagger->getContent(), true);
            foreach ($apiDoc['paths'] as $path => $methods) {
                echo '<div class="row"><h5>' . $path . '</h5>';
                $result = $this->developersService->testApiWithExamples($path, $methods);
                foreach ($result[$path] as $server => $item) {
                    echo '<div class="col-6"><h5 class="bg-';
                    switch ($item['code']) {
                        case 200:
                            echo 'success';
                            break;
                        case 404:
                            echo 'danger';
                            break;
                        default:
                            echo 'info';
                            break;
                    }
                    echo '">' . $server . ' - ' . $item['code'] . '</h5>
                        <a href="' . $item['url'] . '">' . $item['url'] . '</a>';

                    if ($item['content'] !== null) {
                        echo '<p>' . $item['content'] . '</p>';
                    }
                    echo '</div>';
                }

                echo '</div>';
                @ob_flush();
                flush();
            }
            echo "</div></body></html>";
        });
    }

}
