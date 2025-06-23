<?php declare(strict_types=1);

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\HttpClient\Exception\TimeoutExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;


class DevelopersService
{
    public const array Domains = ["https://jacqservicestest.dyn.cloud.e-infra.cz/", "https://services.jacq.org/jacq-"];

    public function __construct(protected readonly EntityManagerInterface $entityManager, protected HttpClientInterface $client, protected RouterInterface $router)
    {
    }

    public function testApiWithExamples(): array
    {
        $responseSwagger = $this->client->request('GET', 'https://jacqservicestest.dyn.cloud.e-infra.cz/doc.json');
        $apiDoc = json_decode($responseSwagger->getContent(), true);

        $pendingMap = [];
        $results = [];

        foreach ($apiDoc['paths'] as $path => $methods) {
            foreach ($methods as $method => $details) {
                if (strtolower($method) !== 'get') {
                    continue;
                }
                foreach (self::Domains as $domain) {
                    $rawRequest = $this->prepareRequest($domain . ltrim($path, '/'), $details);

                    $response = $this->client->request(strtoupper($method), $rawRequest['path'], [
                        'query' => $rawRequest['parameters'],
                        'headers' => ['Accept' => 'application/json'],
                        'timeout' => 15.0,
                        'max_duration' => 15.0
                    ]);

                    $hash = spl_object_hash($response);
                    $pendingMap[$hash] = [
                        'path' => $path,
                        'domain' => $domain,
                        'response' => $response,
                    ];

                    $results[$path][$domain] = null;
                }
            }
        }

        foreach ($pendingMap as $path => $domains) {
            foreach ($domains as $domain => $response) {
                try {
                    $statusCode = $response->getStatusCode();
                    $url = $response->getInfo('url');
                    $content = $statusCode === 200 ? htmlspecialchars(substr($response->getContent(), 0, 200)) : '';

                    $results[$path][$domain] = [
                        'status' => $statusCode,
                        'url' => $url,
                        'content' => $content,
                    ];
                } catch (TimeoutExceptionInterface $e) {
                    $results[$path][$domain] = [
                        'status' => 408,
                        'url' => '',
                        'content' => 'Timeout',
                    ];
                } catch (TransportExceptionInterface $e) {
                    $results[$path][$domain] = [
                        'status' => 500,
                        'url' => '',
                        'content' => 'Transport error',
                    ];
                }
            }
        }

        return $results;
    }

    protected function prepareRequest($path, $details)
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
        return ["path" => $url, "parameters" => $queryParams];
    }


}
