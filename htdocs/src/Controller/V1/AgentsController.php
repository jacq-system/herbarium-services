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
use OpenApi\Attributes\PathParameter;
use OpenApi\Attributes\Post;
use OpenApi\Attributes\Property;
use OpenApi\Attributes\RequestBody;
use OpenApi\Attributes\Schema;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AgentsController extends AbstractFOSRestController
{
    public function __construct(protected readonly ElasticsearchService $elasticsearchService)
    {
    }

    #[Get(
        path: '/v1/agents/collector/{term}',
        summary: 'Get scored proposals for collector names',
        tags: ['agents'],
        parameters: [
            new PathParameter(
                name: 'term',
                description: 'Collectors name',
                in: 'path',
                required: true,
                schema: new Schema(type: 'string'),
                example: 'Reiner,H.'
            )
        ],
        responses: [
            new \OpenApi\Attributes\Response(
                response: 200,
                description: 'Results',
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
    #[Route('/v1/agents/collector/{term}', name: "services_rest_agents_collector", methods: ['GET'])]
    public function collector(string $term): Response
    {


        $data = $this->elasticsearchService->search(ElasticsearchCollectorRefreshCommand::IndexName, $term);

            $result  =
                 array_map(function ($hit) use ($term) {
                    return [
                        "id" => $hit["_id"],
                        "name" => $hit["_source"]["name"],
                        "score" => $hit["_score"],
                        "match" => strtolower($term) === strtolower($hit["_source"]["name"])
                    ];
                }, $data["hits"]["hits"])
            ;


        $view = $this->view($result, 200);
        $view->setFormat('json');

        return $this->handleView($view);
    }

}
