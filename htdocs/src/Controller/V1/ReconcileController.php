<?php declare(strict_types=1);

namespace App\Controller\V1;

use App\Command\ElasticsearchCollectorRefreshCommand;
use App\Service\ElasticsearchService;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use OpenApi\Attributes\AdditionalProperties;
use OpenApi\Attributes\Get;
use OpenApi\Attributes\Items;
use OpenApi\Attributes\JsonContent;
use OpenApi\Attributes\MediaType;
use OpenApi\Attributes\Post;
use OpenApi\Attributes\Property;
use OpenApi\Attributes\RequestBody;
use OpenApi\Attributes\Schema;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ReconcileController extends AbstractFOSRestController
{
    public function __construct(protected readonly ElasticsearchService $elasticsearchService)
    {
    }

    #[Post(
        path: '/v1/reconcile/collector',
        summary: 'Get reconciliation proposals for collector names (bulk)',
        requestBody: new RequestBody(
            description: 'OpenRefine reconciliation queries',
            required: true,
            content: [
                new MediaType(
                    mediaType: 'application/json',
                    schema: new Schema(
                        properties: [
                            new Property(
                                property: 'queries',
                                type: 'object',
                                additionalProperties: new AdditionalProperties(
                                    properties: [
                                        new Property(property: 'query', type: 'string', example: 'H. Reiner')
                                    ],
                                    type: 'object'
                                )
                            )
                        ],
                        type: 'object'
                    )
                )
            ]
        ),
        tags: ['reconcile'],
        responses: [
            new \OpenApi\Attributes\Response(
                response: 200,
                description: 'Reconciliation results',
                content: [
                    new MediaType(
                        mediaType: 'application/json',
                        schema: new Schema(
                            type: 'object',
                            additionalProperties: new AdditionalProperties(
                                properties: [
                                    new Property(
                                        property: 'result',
                                        type: 'array',
                                        items: new Items(
                                            properties: [
                                                new Property(property: 'id', type: 'integer', example: 43237),
                                                new Property(property: 'name', type: 'string', example: 'Reiner,H.'),
                                                new Property(property: 'score', type: 'number', format: 'float', example: 21.43),
                                                new Property(property: 'match', type: 'boolean', example: false),
                                                new Property(
                                                    property: 'type',
                                                    type: 'array',
                                                    items: new Items(type: 'string'),
                                                    example: ['Person']
                                                ),
                                                new Property(property: 'uri', type: 'string', example: 'https://x')
                                            ],
                                            type: 'object'
                                        )
                                    )
                                ],
                                type: 'object'
                            )
                        )
                    )
                ]
            ),
            new \OpenApi\Attributes\Response(
                response: 400,
                description: 'Bad Request'
            )
        ]
    )]
    #[Route('/v1/reconcile/collector', name: "services_rest_reconcile_collector", methods: ['POST'])]
    public function collector(Request $request): Response
    {
        $result = [];
        $payload = json_decode($request->getContent(), true)["queries"] ?? [];

        foreach ($payload as $key => $q) {
            $term = $q["query"] ?? "";
            $data = $this->elasticsearchService->search(ElasticsearchCollectorRefreshCommand::IndexName, $term);

            $result[$key] = [
                "result" => array_map(function ($hit) use ($term) {
                    return [
                        "id" => $hit["_id"],
                        "name" => $hit["_source"]["name"],
                        "score" => $hit["_score"],
                        "match" => strtolower($term) === strtolower($hit["_source"]["name"]),
                        "type" => [
                            ["id" => "Person", "name" => "Person"]
                        ],
                        "uri" => "https://example.com/entity/" . $hit["_id"]
                    ];
                }, $data["hits"]["hits"])
            ];
        }

        $view = $this->view($result, 200);
        $view->setFormat('json');

        return $this->handleView($view);
    }


    #[Get(
        path: '/v1/reconcile/collector',
        description: 'Returns service metadata required by OpenRefine reconciliation API.',
        summary: 'Reconciliation service manifest',
        tags: ['reconcile'],
        responses: [
            new \OpenApi\Attributes\Response(
                response: 200,
                description: 'Reconciliation manifest',
                content: new JsonContent(
                    properties: [
                        new Property(
                            property: 'versions',
                            type: 'array',
                            items: new Items(type: 'string'),
                            example: ['0.2']
                        ),
                        new Property(property: 'name', type: 'string', example: 'JACQ Collectors Reconciliation Service'),
                        new Property(property: 'identifierSpace', type: 'string', example: 'https://example.com/entity/'),
                        new Property(property: 'schemaSpace', type: 'string', example: 'http://schema.org/Thing'),
                        new Property(
                            property: 'types',
                            type: 'array',
                            items: new Items(
                                properties: [
                                    new Property(property: 'id', type: 'string', example: 'Person'),
                                    new Property(property: 'name', type: 'string', example: 'Person')
                                ],
                                type: 'object'
                            )
                        )
                    ]
                )
            )
        ]
    )]
    #[Route('/v1/reconcile/collector', name: 'reconcile_manifest', methods: ['GET'])]
    public function manifest(): JsonResponse
    {
        return new JsonResponse([
            'versions' => ['0.2'],
            'name' => 'JACQ Collectors Reconciliation Service TEST',
            'identifierSpace' => 'https://jacq.org/entity/',
            'schemaSpace' => 'https://jacq.org/Collector',
            'defaultTypes'=>  [
                'id' => 'Person',
                'name' => 'Person'
            ]
        ]);
    }
}
