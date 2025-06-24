<?php declare(strict_types=1);

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;


class DevelopersService
{
    public const array Domains = ["https://jacqservicestest.dyn.cloud.e-infra.cz/", "https://services.jacq.org/jacq-"];

    public function __construct(protected readonly EntityManagerInterface $entityManager, protected HttpClientInterface $client, protected RouterInterface $router)
    {
    }

    public function getExampleLinks(): array
    {
        $responseSwagger = $this->client->request('GET', 'https://jacqservicestest.dyn.cloud.e-infra.cz/doc.json');
        $apiDoc = json_decode($responseSwagger->getContent(), true);

        $results = [];

        foreach ($apiDoc['paths'] as $path => $methods) {
            foreach ($methods as $method => $details) {
                if (strtolower($method) !== 'get') {
                    continue;
                }
                foreach (self::Domains as $domain) {
                    $url = $this->prepareRequest($domain . ltrim($path, '/'), $details);

                    $results[$path][$domain] = $url;
                }
            }
        }

        return $results;
    }

    protected function prepareRequest($path, $details): string
    {
        $url = $path;
        $queryParams = [];
        $pathParams = [];

        if (isset($details['parameters'])) {
            foreach ($details['parameters'] as $parameter) {
                $paramName = $parameter['name'];
                $exampleValue = $parameter['example'] ?? '';

                if ($parameter['in'] === 'path') {
                    $pathParams[$paramName] = $exampleValue;
                } elseif ($parameter['in'] === 'query') {
                    $queryParams[$paramName] = $exampleValue;
                }
            }
        }

        //  /users/{id} → /users/1
        foreach ($pathParams as $name => $value) {
            $url = str_replace('{' . $name . '}', (string)$value, $url);
        }

        if (!empty($queryParams)) {
            $url .= '?' . http_build_query($queryParams);
        }

        return $url;
    }


}
