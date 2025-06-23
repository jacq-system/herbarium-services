<?php declare(strict_types=1);

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\HttpClient\Exception\TimeoutExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;


class DevelopersService
{
    protected const array Domains = ["https://jacqservicestest.dyn.cloud.e-infra.cz/", "https://services.jacq.org/jacq-"];

    public function __construct(protected readonly EntityManagerInterface $entityManager, protected HttpClientInterface $client, protected RouterInterface $router)
    {
    }

    public function testApiWithExamples(string $path, array $methods): array
    {
        $results=[];
            foreach ($methods as $method => $details) {
                if ($method !== 'get') {
                    /** testing only GET to be easy */
                    continue;
                }
                foreach (self::Domains as $domain) {
                    $rawRequest = $this->prepareRequest($domain . ltrim($path, '/'), $details);
                    try {
                        $individualResponse = $this->client->request(strtoupper($method), $rawRequest["path"], ["query" => $rawRequest["parameters"], 'headers' => [
                            'Accept' => 'application/json',
                            "timeout" => 2.0
                        ]]);
                        $statusCode = $individualResponse->getStatusCode();
                        $result = [
                            "code" => $statusCode,
                            "content-type" => $statusCode === 200 ? $individualResponse->getHeaders()['content-type'][0] : '',
                            "content" => $statusCode === 200 ? $individualResponse->getContent() : '',
                            "url" => $individualResponse->getInfo("url")
                        ];

                    } catch (TimeoutExceptionInterface $e) {
                        $result = [
                            "code" => 408,  // HTTP 408 Request Timeout
                            "content-type" => '',
                            "content" => '',
                            "url" => $rawRequest["path"]
                        ];
                    } catch (TransportExceptionInterface $e) {
                        $result = [
                            "code" => 500,
                            "content-type" => '',
                            "content" => '',
                            "url" => $rawRequest["path"]
                        ];
                    }
                    $results[$path][$domain] = $result;
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
