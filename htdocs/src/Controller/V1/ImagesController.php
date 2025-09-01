<?php declare(strict_types=1);

namespace App\Controller\V1;

use FOS\RestBundle\Controller\AbstractFOSRestController;
use JACQ\Service\Legacy\ImageLinkMapper;
use OpenApi\Attributes\Get;
use OpenApi\Attributes\MediaType;
use OpenApi\Attributes\PathParameter;
use OpenApi\Attributes\Property;
use OpenApi\Attributes\QueryParameter;
use OpenApi\Attributes\Schema;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;

class ImagesController extends AbstractFOSRestController
{
    public function __construct(protected readonly ImageLinkMapper $imageLinkMapper)
    {
    }

    #[Get(
        path: '/v1/images/show/{specimenID}/{imageNr}',
        summary: 'get the uri to show the first image of a given specimen-ID',
        tags: ['images'],
        parameters: [
            new PathParameter(
                name: 'specimenID',
                description: 'ID of specimen',
                in: 'path',
                required: true,
                schema: new Schema(type: 'integer'),
                example: 4354
            ),
            new PathParameter(
                name: 'imageNr',
                description: 'image number (defaults to 0=first image)',
                in: 'path',
                required: true,
                schema: new Schema(type: 'integer'),
                example: 1
            ),
            new QueryParameter(
                name: 'withredirect',
                description: 'returns http 303 and redirects to the link immediately',
                required: false,
                schema: new Schema(type: 'boolean'),
            )
        ],
        responses: [
            new \OpenApi\Attributes\Response(
                response: 200,
                description: 'uri',
                content: [new MediaType(
                    mediaType: 'application/json',
                    schema: new Schema(
                        properties: [
                            new Property(property: 'imagelink')
                        ]
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
    #[Route('/v1/images/show/{specimenID}/{imageNr}', name: "services_rest_images_show", methods: ['GET'])]
    public function show(int $specimenID, int $imageNr = 0, #[MapQueryParameter] ?bool $withredirect = false): Response
    {
        $this->imageLinkMapper->setSpecimen($specimenID);
        $results['link'] = $this->imageLinkMapper->getShowLink($imageNr);
        if ($withredirect) {
            return $this->redirect($results['link'], 303);
        }

        $view = $this->view($results, 200);
        return $this->handleView($view);
    }

    #[Get(
        path: '/v1/images/download/{specimenID}/{imageNr}',
        summary: 'et the uri to show the first image of a given specimen-ID',
        tags: ['images'],
        parameters: [
            new PathParameter(
                name: 'specimenID',
                description: 'ID of specimen',
                in: 'path',
                required: true,
                schema: new Schema(type: 'integer'),
                example: 4354
            ),
            new PathParameter(
                name: 'imageNr',
                description: 'image number (defaults to 0=first image)',
                in: 'path',
                required: true,
                schema: new Schema(type: 'integer'),
                example: 1
            ),
            new QueryParameter(
                name: 'withredirect',
                description: 'returns http 303 and redirects to the link immediately',
                required: false,
                schema: new Schema(type: 'boolean'),
            )
        ],
        responses: [
            new \OpenApi\Attributes\Response(
                response: 200,
                description: 'uri',
                content: [new MediaType(
                    mediaType: 'application/json',
                    schema: new Schema(
                        properties: [
                            new Property(property: 'imagelink')
                        ]
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
    #[Route('/v1/images/download/{specimenID}/{imageNr}', name: "services_rest_images_download", methods: ['GET'])]
    public function download(int $specimenID, int $imageNr = 0, #[MapQueryParameter] ?bool $withredirect = false): Response
    {
        $this->imageLinkMapper->setSpecimen($specimenID);
        $results['link'] = $this->imageLinkMapper->getDownloadLink($imageNr);

        if ($withredirect) {
            return $this->redirect($results['link'], 303);
        }

        $view = $this->view($results, 200);

        return $this->handleView($view);
    }

    #[Get(
        path: '/v1/images/europeana/{specimenID}/{imageNr}',
        summary: 'get the uri to download the first image of a given specimen-ID with resolution 1200',
        tags: ['images'],
        parameters: [
            new PathParameter(
                name: 'specimenID',
                description: 'ID of specimen',
                in: 'path',
                required: true,
                schema: new Schema(type: 'integer'),
                example: 4354
            ),
            new PathParameter(
                name: 'imageNr',
                description: 'image number (defaults to 0=first image)',
                in: 'path',
                required: true,
                schema: new Schema(type: 'integer'),
                example: 1
            ),
            new QueryParameter(
                name: 'withredirect',
                description: 'returns http 303 and redirects to the link immediately',
                required: false,
                schema: new Schema(type: 'boolean'),
            )
        ],
        responses: [
            new \OpenApi\Attributes\Response(
                response: 200,
                description: 'links',
                content: [new MediaType(
                    mediaType: 'application/json',
                    schema: new Schema(
                        properties: [
                            new Property(property: 'imagelink')
                        ]
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
    #[Route('/v1/images/europeana/{specimenID}/{imageNr}', name: "services_rest_images_europeana", methods: ['GET'])]
    public function europeana(int $specimenID, int $imageNr = 0, #[MapQueryParameter] ?bool $withredirect = false): Response
    {
        $this->imageLinkMapper->setSpecimen($specimenID);
        $results['link'] = $this->imageLinkMapper->getEuropeanaLink($imageNr);

        if ($withredirect) {
            return $this->redirect($results['link'], 303);
        }

        $view = $this->view($results, 200);

        return $this->handleView($view);
    }

    #[Get(
        path: '/v1/images/thumb/{specimenID}/{imageNr}',
        summary: 'get the uri to download the first image of a given specimen-ID with resolution 160',
        tags: ['images'],
        parameters: [
            new PathParameter(
                name: 'specimenID',
                description: 'ID of specimen',
                in: 'path',
                required: true,
                schema: new Schema(type: 'integer'),
                example: 4354
            ),
            new PathParameter(
                name: 'imageNr',
                description: 'image number (defaults to 0=first image)',
                in: 'path',
                required: true,
                schema: new Schema(type: 'integer'),
                example: 1
            ),
            new QueryParameter(
                name: 'withredirect',
                description: 'returns http 303 and redirects to the link immediately',
                required: false,
                schema: new Schema(type: 'boolean'),
            )
        ],
        responses: [
            new \OpenApi\Attributes\Response(
                response: 200,
                description: 'links',
                content: [new MediaType(
                    mediaType: 'application/json',
                    schema: new Schema(
                        properties: [
                            new Property(property: 'imagelink')
                        ]
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
    #[Route('/v1/images/thumb/{specimenID}/{imageNr}', name: "services_rest_images_thumb", methods: ['GET'])]
    public function thumb(int $specimenID, int $imageNr = 0, #[MapQueryParameter] ?bool $withredirect = false): Response
    {
        $this->imageLinkMapper->setSpecimen($specimenID);
        $results['link'] = $this->imageLinkMapper->getThumbLink($imageNr);

        if ($withredirect) {
            return $this->redirect($results['link'], 303);
        }

        $view = $this->view($results, 200);

        return $this->handleView($view);
    }

    #[Get(
        path: '/v1/images/list/{specimenID}',
        summary: 'get a list of all image-uris of a given specimen-ID',
        tags: ['images'],
        parameters: [
            new PathParameter(
                name: 'specimenID',
                description: 'ID of specimen',
                in: 'path',
                required: true,
                schema: new Schema(type: 'integer'),
                example: 4354
            )
        ],
        responses: [
            new \OpenApi\Attributes\Response(
                response: 200,
                description: 'links',
                content: [new MediaType(
                    mediaType: 'application/json',
                    schema: new Schema(
                        properties: [
                            new Property(property: 'imagelink')
                        ]
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
    #[Route('/v1/images/list/{specimenID}', name: "services_rest_images_list", methods: ['GET'])]
    public function list(int $specimenID, int $imageNr = 0): Response
    {
        $this->imageLinkMapper->setSpecimen($specimenID);
        $results = $this->imageLinkMapper->getList();

        $view = $this->view($results, 200);

        return $this->handleView($view);
    }
}
