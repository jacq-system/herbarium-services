<?php declare(strict_types=1);

namespace App\Service;

use JACQ\Repository\Herbarinput\CollectorRepository;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ElasticsearchService
{

    public function __construct(protected readonly string $basePath, protected readonly CollectorRepository $collectorRepository, protected HttpClientInterface $client)
    {
    }

    public function recreateIndex(string $index): void
    {
        // delete if exists
        try {
            $this->client->request('DELETE', $this->basePath . $index);
        } catch (ClientExceptionInterface $e) {
            // if the index does not exist yet
            if ($e->getResponse()->getStatusCode() !== 404) {
                throw $e;
            }
        }

        // recreate empty index
        $this->client->request("PUT", $this->basePath . $index, [
            'json' => [
                'settings' => [
                    'number_of_shards' => 1,
                    'number_of_replicas' => 0,
                    'refresh_interval' => '-1',
                ],
                'mappings' => [
                    'properties' => [
                        'name' => [
                            'type' => 'text',
                            'analyzer' => 'standard'
                        ],
                    ],
                ],
            ]
        ]);
    }

    public function bulk(array $lines): void
    {
        $body = implode("\n", $lines) . "\n";

        $this->client->request("POST", $this->basePath . "_bulk", [
            "headers" => ["Content-Type" => "application/json"],
            "body" => $body,
        ]);
    }

    public function refreshIndex(string $index): void
    {
        $this->client->request("POST", $this->basePath . $index . "/_refresh");
    }


    public function search(string $index, string $query, int $limit = 5): array
    {
        $body = [
            "query" => [
                "multi_match" => [
                    "query" => $query,
                    "fields" => ["name^2"],
                    "fuzziness" => "AUTO"
                ]
            ],
            "size" => $limit
        ];

        $response = $this->client->request("GET", $this->basePath . $index . "/_search", [
            "json" => $body
        ]);

//        dump($response->getStatusCode());
//        dump($response->getHeaders());
//        dump($response->getContent(false));
//        dump($response->getInfo());
//        exit;

        return $response->toArray();
    }

}
