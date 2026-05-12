<?php

declare(strict_types=1);

namespace App\Service;

use JACQ\Entity\Jacq\Herbarinput\ExternalServices;
use JACQ\Repository\Herbarinput\ExternalServicesRepository;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ExternalScientificNamesService
{
    public const int ID_GBIF = 51;
    public const int ID_WFO = 57;
    public const int ID_WORMS = 58;

    /**
     * @var mixed[]
     */
    protected array $result = [];

    /**
     * @var mixed[]
     */
    protected array $apiResponses = [];

    /**
     * @var mixed[]
     */
    protected array $errors = [];
    protected string $searchString = '';

    public function __construct(protected readonly ExternalServicesRepository $servicesRepository, protected HttpClientInterface $httpClient) {}

    /**
     * @return mixed[]
     */
    public function searchAll(string $term): array
    {
        $this->searchString = $term;

        foreach ($this->servicesRepository->getCallableServices() as $externalService) {
            try {
                $this->apiResponses[$externalService->id] = $this->httpClient->request('GET', $externalService->apiUrl.urlencode($term), [
                    'timeout' => 8,
                ]);
            } catch (TransportExceptionInterface $e) {
                $this->errors[$externalService->id] = $e->getMessage();
            } finally {
                $this->result[$externalService->apiCode] = [
                    'match' => [],
                    'candidates' => [],
                    'serviceID' => $externalService->id,
                    'name' => $externalService->name,
                    'error' => $this->errors[$externalService->id] ?? null,
                ];
            }

            $this->proceedResponse($externalService);
        }

        return [
            'searchString' => $this->searchString,
            'results' => $this->result,
        ];
    }

    protected function proceedResponse(ExternalServices $service): void
    {
        try {
            $response = $this->apiResponses[$service->id];
            $result = json_decode($response->getContent(), true, 512, JSON_INVALID_UTF8_SUBSTITUTE);

            switch ($service->id) {
                case self::ID_GBIF:
                    $this->gbif_read($service, $result);

                    break;

                case self::ID_WFO:
                    $this->wfo_read($service, $result);

                    break;

                case self::ID_WORMS:
                    if (200 === $response->getStatusCode()) {
                        $this->worms_read($service, $result);
                    }

                    break;
            }
        } catch (\Throwable $e) {
            $this->result[$service->name]['error'] = $e->getMessage();
        }
    }

    /**
     * read GBIF and store data into internal array, needs just the result of the service.
     *
     * @param mixed[] $result given result of the service, json decoded
     */
    private function gbif_read(ExternalServices $service, array $result): void
    {
        if (isset($result['count']) && $result['count'] > 0) {
            if (1 === $result['count']) {
                $this->result[$service->apiCode]['match'] = ['id' => $result['results'][0]['key'],
                    'name' => $result['results'][0]['scientificName']];
            } else {
                foreach ($result['results'] as $candidate) {
                    $this->result[$service->apiCode]['candidates'][] = ['id' => $candidate['key'],
                        'name' => $candidate['scientificName']];
                }
            }
        }
    }

    /**
     * read World Flora Online and store data into internal array, needs just the result of the service.
     *
     * @param mixed[] $result given result of the service, json decoded
     */
    private function wfo_read(ExternalServices $service, array $result): void
    {
        if (!empty($result['match'])) {
            $this->result[$service->apiCode]['match'] = ['id' => $result['match']['wfo_id'],
                'name' => $result['match']['full_name_plain']];
        } elseif (!empty($result['candidates'])) {
            foreach ($result['candidates'] as $candidate) {
                $this->result[$service->apiCode]['candidates'][] = ['id' => $candidate['wfo_id'],
                    'name' => $candidate['full_name_plain']];
            }
        }
    }

    /**
     *  read World Register of Marine Species (VLIZ) and store data into internal array, needs just the result of the service.
     *
     * @param mixed[] $result given result of the service, json decoded
     */
    private function worms_read(ExternalServices $service, array $result): void
    {
        if (count($result) > 1) {
            foreach ($result as $candidate) {
                $this->result[$service->apiCode]['candidates'][] = ['id' => $candidate['AphiaID'],
                    'name' => $candidate['scientificname']];
            }
        } else {
            $this->result[$service->apiCode]['match'] = ['id' => $result[0]['AphiaID'],
                'name' => $result[0]['scientificname']];
        }
    }
}
