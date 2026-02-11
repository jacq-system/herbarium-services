<?php declare(strict_types=1);

namespace App\Controller\V1;

use App\Facade\ObjectsFacade;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Exception;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use JACQ\Application\Specimen\Export\ExcelService;
use JACQ\Application\Specimen\Search\SpecimenSearchQueryFactory;
use JACQ\Service\SpecimenService;
use JACQ\UI\Http\SpecimenSearchParametersFromRequestFactory;
use OpenApi\Attributes\Header;
use OpenApi\Attributes\Get;
use OpenApi\Attributes\Items;
use OpenApi\Attributes\MediaType;
use OpenApi\Attributes\PathParameter;
use OpenApi\Attributes\Property;
use OpenApi\Attributes\QueryParameter;
use OpenApi\Attributes\Schema;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Writer\Ods;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;

class ObjectsController extends AbstractFOSRestController
{
    public function __construct(protected readonly ObjectsFacade $objectsFacade, protected readonly SpecimenService $specimenService, protected LoggerInterface $logger, protected SpecimenSearchParametersFromRequestFactory $fromRequestFactory, protected SpecimenSearchQueryFactory $searchQueryFactory, protected ExcelService $excelService, protected EntityManagerInterface $entityManager)
    {
    }

    #[Get(
        path: '/v1/objects/specimens/{specimenID}',
        summary: 'get the properties of a specimen',
        tags: ['objects'],
        parameters: [
            new PathParameter(
                name: 'specimenID',
                description: 'ID of specimen',
                in: 'path',
                required: true,
                schema: new Schema(type: 'integer'),
                example: 316368
            )
        ],
        responses: [
            new \OpenApi\Attributes\Response(
                response: 200,
                description: 'List',
                content: [new MediaType(
                    mediaType: 'application/json',
                    schema: new Schema(
                        type: 'array',
                        items: new Items(
                            properties: [
                                new Property(property: 'results', type: 'object')
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
    #[Route('/v1/objects/specimens/{specimenID<\d+>}', name: "services_rest_objects_specimen", methods: ['GET'])]
    public function specimen(int $specimenID): Response
    {
        try {
            $specimen = $this->specimenService->findAccessibleForPublic($specimenID);
            $data = $this->objectsFacade->resolveSpecimen($specimen);
        } catch (Exception $e) {
            try {
                $specimen = $this->specimenService->findNonAccessibleForPublic($specimenID);
                $data = $this->objectsFacade->resolveSpecimen($specimen);
            } catch (Exception $e) {
                $view = $this->view([], 404);
                return $this->handleView($view);
            }
        }

        $view = $this->view($data, 200);

        return $this->handleView($view);
    }

    #[Get(
        path: '/v1/objects/specimens/by-sid/{sid}',
        summary: 'get the properties of a specimen identified by SID',
        tags: ['objects'],
        parameters: [
            new PathParameter(
                name: 'sid',
                description: 'stable identifier of specimen',
                in: 'path',
                required: true,
                schema: new Schema(type: 'string'),
                example: 'https://wu.jacq.org/WU-0000264'
            )
        ],
        responses: [
            new \OpenApi\Attributes\Response(
                response: 200,
                description: 'List',
                content: [new MediaType(
                    mediaType: 'application/json',
                    schema: new Schema(
                        type: 'array',
                        items: new Items(
                            properties: [
                                new Property(property: 'results', type: 'object')
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
    #[Route('/v1/objects/specimens/by-sid/{sid<.+>}', name: "services_rest_objects_specimen_bysid", methods: ['GET'])]
    public function specimenBySid(string $sid): Response
    {
        $sid = urldecode($sid);
        $sid = $this->fixSchemeSlashes($sid);
        $specimen = $this->specimenService->findSpecimenUsingSid($sid);
        if ($specimen === null) {
            $view = $this->view([], 404);
            return $this->handleView($view);
        }

        $data = $this->objectsFacade->resolveSpecimen($specimen);
        $view = $this->view($data, 200);

        return $this->handleView($view);
    }

    #[Get(
        path: '/v1/objects/specimens',
        summary: 'search for all specimens which fit given criteria',
        tags: ['objects'],
        parameters: [
            new QueryParameter(
                name: 'p',
                description: 'optional number of page to display, starts with 0 (first page), defaults to 0',
                in: 'query',
                required: false,
                schema: new Schema(type: 'integer'),
                example: 2
            ),
            new QueryParameter(
                name: 'rpp',
                description: 'optional number of records per page to display (<= 100), defaults to 50',
                in: 'query',
                required: false,
                schema: new Schema(type: 'integer'),
                example: 6
            ),
            new QueryParameter(
                name: 'list',
                description: 'optional switch if all specimen data should be returned (=0) or just a list of specimen-IDs (=1), defaults to 1',
                in: 'query',
                required: false,
                schema: new Schema(type: 'integer'),
                example: 1
            ),
            new QueryParameter(
                name: 'term',
                description: 'optional search term for scientific names, use * as a wildcard, multiple terms seperated by \',\'',
                in: 'query',
                required: false,
                schema: new Schema(type: 'string'),
                example: "prunus av*"
            ),
            new QueryParameter(
                name: 'sc',
                description: 'optional search term for source codes, case insensitive',
                in: 'query',
                required: false,
                schema: new Schema(type: 'string')
            ),
            new QueryParameter(
                name: 'coll',
                description: 'optional search term for collector(s), case insensitive',
                in: 'query',
                required: false,
                schema: new Schema(type: 'string'),
                example: "rainer"
            ),
            new QueryParameter(
                name: 'type',
                description: 'optional switch to search for type records only, defaults to 0',
                in: 'query',
                required: false,
                schema: new Schema(type: 'integer'),
                example: 0
            ),
            new QueryParameter(
                name: 'sort',
                description: 'optional sorting of results, seperated by commas, \'-\' as first character changes sorting to DESC, possible items are sciname (scientific name), coll (collector(s)), ser (series), num (collectors number), herbnr (herbarium number)',
                in: 'query',
                required: false,
                schema: new Schema(type: 'string'),
                example: ""
            ),
            new QueryParameter(
                name: 'herbnr',
                description: '',
                in: 'query',
                required: false,
                schema: new Schema(type: 'string')
            ),
            new QueryParameter(
                name: 'nation',
                description: '',
                in: 'query',
                required: false,
                schema: new Schema(type: 'string')
            ),
            new QueryParameter(
                name: 'withImages',
                description: '',
                in: 'query',
                required: false,
                schema: new Schema(type: 'integer'),
                example: 0
            ),
            new QueryParameter(
                name: 'cltr',
                description: 'collector',
                in: 'query',
                required: false,
                schema: new Schema(type: 'string')
            ),
        ],
        responses: [
            new \OpenApi\Attributes\Response(
                response: 200,
                description: 'List',
                content: [new MediaType(
                    mediaType: 'application/json',
                    schema: new Schema(
                        type: 'array',
                        items: new Items(
                            properties: [
                                new Property(property: 'results', type: 'object')
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
    #[Route('/v1/objects/specimens', name: "services_rest_objects_specimens", methods: ['GET'])]
    public function specimens(#[MapQueryParameter] ?int $p = 0, #[MapQueryParameter] ?int $rpp = 50, #[MapQueryParameter] ?int $list = 1, #[MapQueryParameter] ?string $term = '', #[MapQueryParameter] ?string $sc = '', #[MapQueryParameter] ?string $coll = '', #[MapQueryParameter] ?int $type = 0, #[MapQueryParameter] ?string $sort = '', #[MapQueryParameter] ?string $herbnr = '', #[MapQueryParameter] ?string $nation = '', #[MapQueryParameter] ?int $withImages = 0, #[MapQueryParameter] ?string $cltr = ''): Response
    {
        ($rpp > 100) ? $rpp = 100 : null;
        $data = $this->objectsFacade->resolveSpecimens($p, $rpp, $list, $term, $sc, $coll, $type, $sort, $herbnr, $nation, $withImages, $cltr, false);
        $view = $this->view($data, 200);

        return $this->handleView($view);
    }

    #[Get(
        path: '/v1/objects/specimens/export',
        summary: 'export specimens which fit given criteria, a limit 1000 rows is applied',
        tags: ['objects'],
        parameters: [

            new QueryParameter(
                name: 'institution',
                description: 'ID of the institution',
                in: 'query',
                required: false,
                schema: new Schema(type: 'integer'),
                example: 5
            ),
            new QueryParameter(
                name: 'herbNr',
                description: 'Herbarium number',
                in: 'query',
                required: false,
                schema: new Schema(type: 'string')
            ),
            new QueryParameter(
                name: 'collection',
                description: 'ID of the collection',
                in: 'query',
                required: false,
                schema: new Schema(type: 'integer')
            ),
            new QueryParameter(
                name: 'collectorNr',
                description: 'Collector number',
                in: 'query',
                required: false,
                schema: new Schema(type: 'string')
            ),
            new QueryParameter(
                name: 'collector',
                description: 'Collector name',
                in: 'query',
                required: false,
                schema: new Schema(type: 'string')
            ),
            new QueryParameter(
                name: 'collectionDate',
                description: 'Date of collection (YYYY-MM-DD)',
                in: 'query',
                required: false,
                schema: new Schema(type: 'string', format: 'date')
            ),
            new QueryParameter(
                name: 'collectionNr',
                description: 'Collection ID',
                in: 'query',
                required: false,
                schema: new Schema(type: 'string')
            ),
            new QueryParameter(
                name: 'series',
                description: 'Series name',
                in: 'query',
                required: false,
                schema: new Schema(type: 'string')
            ),
            new QueryParameter(
                name: 'locality',
                description: 'Locality description',
                in: 'query',
                required: false,
                schema: new Schema(type: 'string')
            ),
            new QueryParameter(
                name: 'habitus',
                description: 'Plant habitus',
                in: 'query',
                required: false,
                schema: new Schema(type: 'string')
            ),
            new QueryParameter(
                name: 'habitat',
                description: 'Habitat description',
                in: 'query',
                required: false,
                schema: new Schema(type: 'string')
            ),
            new QueryParameter(
                name: 'taxonAlternative',
                description: 'Alternative taxon name',
                in: 'query',
                required: false,
                schema: new Schema(type: 'string')
            ),
            new QueryParameter(
                name: 'annotation',
                description: 'Annotation text',
                in: 'query',
                required: false,
                schema: new Schema(type: 'string')
            ),
            new QueryParameter(
                name: 'country',
                description: 'Country name',
                in: 'query',
                required: false,
                schema: new Schema(type: 'string')
            ),
            new QueryParameter(
                name: 'province',
                description: 'Province name',
                in: 'query',
                required: false,
                schema: new Schema(type: 'string')
            ),
            new QueryParameter(
                name: 'onlyType',
                description: 'Return only type specimens (default false)',
                in: 'query',
                required: false,
                schema: new Schema(type: 'boolean')
            ),
            new QueryParameter(
                name: 'includeSynonym',
                description: 'Include synonyms in search (default false)',
                in: 'query',
                required: false,
                schema: new Schema(type: 'boolean')
            ),
            new QueryParameter(
                name: 'onlyImages',
                description: 'Return only specimens with images (default false)',
                in: 'query',
                required: false,
                schema: new Schema(type: 'boolean')
            ),
            new QueryParameter(
                name: 'family',
                description: 'Family name',
                in: 'query',
                required: false,
                schema: new Schema(type: 'string')
            ),
            new QueryParameter(
                name: 'onlyCoords',
                description: 'Return only specimens with coordinates (default false)',
                in: 'query',
                required: false,
                schema: new Schema(type: 'boolean')
            ),
            new QueryParameter(
                name: 'taxon',
                description: 'Taxon name',
                in: 'query',
                required: false,
                schema: new Schema(type: 'string')
            ),
            new QueryParameter(
                name: 'format',
                description: 'Export format (default xlsx)',
                in: 'query',
                required: false,
                schema: new Schema(
                    type: 'string',
                    default: 'xlsx',
                    enum: ['xlsx', 'ods', 'csv', 'geojson', 'kml']
                ),
                example: 'xlsx'
            )

        ],
        responses: [
            new \OpenApi\Attributes\Response(
                response: 200,
                description: 'Export file (xlsx, csv, ods, geojson or kml)',
                headers: [
                    new Header(
                        header: 'Content-Disposition',
                        description: 'attachment; filename="export.ext"',
                        schema: new Schema(type: 'string')
                    )
                ],
                content: [
                    new MediaType(
                        mediaType: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        schema: new Schema(type: 'string', format: 'binary')
                    ),
                    new MediaType(
                        mediaType: 'text/csv',
                        schema: new Schema(type: 'string', format: 'binary')
                    ),
                    new MediaType(
                        mediaType: 'application/vnd.oasis.opendocument.spreadsheet',
                        schema: new Schema(type: 'string', format: 'binary')
                    ),
                    new MediaType(
                        mediaType: 'application/geo+json',
                        schema: new Schema(type: 'string', format: 'binary')
                    ),
                    new MediaType(
                        mediaType: 'application/vnd.google-earth.kml+xml',
                        schema: new Schema(type: 'string', format: 'binary')
                    ),
                ]
            ),
            new \OpenApi\Attributes\Response(
                response: 400,
                description: 'Bad Request'
            )
        ]
    )]
    #[Route('/v1/objects/specimens/export', name: "services_rest_objects_specimens_export", methods: ['GET'])]
    public function specimensExport(Request $request): Response
    {

        $parameters = $this->fromRequestFactory->create($request);
        $specimenSearchQuery = $this->searchQueryFactory->createForPublic();
        $qb = $specimenSearchQuery->build($parameters);
//        dd($parameters);
//        dd($qb->getQuery()->getDQL(), $qb->getQuery()->getParameters());
        return match ($request->query->get('format')) {
            'xlsx' => $this->provideExcel($qb),
            'ods'  => $this->provideOds($qb),
            'csv'  => $this->provideCsv($qb),
            //TODO GeoJson, KML
            default => throw new \InvalidArgumentException('Unsupported format'),
        };

    }

    protected function provideExcel(QueryBuilder $qb): Response
    {
        $spreadsheet = $this->excelService->createSpecimenExport($qb);

        $response = new StreamedResponse(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        });

        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', 'attachment;filename="specimens_download.xlsx"');
        $response->headers->set('Cache-Control', 'max-age=0');

        return $response;
    }
    protected function provideOds(QueryBuilder $qb): Response
    {
        $spreadsheet = $this->excelService->createSpecimenExport($qb);

        $response = new StreamedResponse(function () use ($spreadsheet) {
            $writer = new Ods($spreadsheet);
            $writer->save('php://output');
        });

        $response->headers->set('Content-Type', 'application/vnd.oasis.opendocument.spreadsheet');
        $response->headers->set('Content-Disposition', 'attachment;filename="specimens_download.ods"');
        $response->headers->set('Cache-Control', 'max-age=0');

        return $response;
    }
    protected function provideCsv(QueryBuilder $qb): Response
    {
        $spreadsheet = $this->excelService->createSpecimenExport($qb);

        $response = new StreamedResponse(function () use ($spreadsheet) {
            $writer = new Csv($spreadsheet);
            $writer->save('php://output');
        });

        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment;filename="specimens_download.csv"');
        $response->headers->set('Cache-Control', 'max-age=0');

        return $response;
    }

    private function fixSchemeSlashes(string $sid): string
    {
        //add slash
        $sid = preg_replace('#^(https?):/([^/])#i', '$1://$2', $sid);
        //reduce to exactly two
        return preg_replace('#^(https?):/{3,}#i', '$1://', $sid);
    }
}
