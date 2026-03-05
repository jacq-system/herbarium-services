<?php declare(strict_types=1);

namespace App\Facade;

use Doctrine\ORM\EntityManagerInterface;
use JACQ\Application\Specimen\Search\SpecimenBatchProvider;
use JACQ\Application\Specimen\Search\SpecimenSearchParameters;
use JACQ\Application\Specimen\Search\SpecimenSearchQueryFactory;
use JACQ\Entity\Jacq\Herbarinput\Specimens;
use JACQ\Service\SpeciesService;
use JACQ\Service\SpecimenService;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

readonly class ObjectsFacade
{
    public function __construct(protected EntityManagerInterface $entityManager, protected RouterInterface $router, protected SpeciesService $taxonService, protected SpecimenService $specimenService, protected SpecimenBatchProvider $specimenBatchProvider, protected SpecimenSearchQueryFactory $searchQueryFactory)
    {
    }

    public function resolveSpecimens(int $currentPage, int $recordsPerPage, bool $returnOnlyIds, SpecimenSearchParameters $parameters): array
    {
        $specimenSearchQuery = $this->searchQueryFactory->createForPublic();
        $totalRows = $specimenSearchQuery->countResults($parameters);
        $offset = $recordsPerPage * ($currentPage-1);

        $originalParameters = [
            'rpp' => $recordsPerPage,              // records per page, default: 50
            'list' => $returnOnlyIds,               // return just a list of specimen-IDs?, default: yes
            'term' => $parameters->taxon,              // search for scientific name (joker = *)
            'herbnr' => $parameters->herbNr,              // search for herbarium number (joker = *)
            'sc' => $parameters->institutionCode,              // search for a source-code
            'cltr' => $parameters->collector,              // search for a collector
            'nation' => $parameters->country,              // search for a nation
            'type' => (int)$parameters->onlyType,               // switch, search only for type records (default: no)
            'withImages' => (int)$parameters->onlyImages,               // switch, search only for records with images (default: no)
            'sort' => implode(',',array_map(
                static fn($column, $direction) =>
                    (strtoupper($direction) === 'DESC' ? '-' : '+') . $column,
                array_keys($parameters->sort),
                $parameters->sort ?? []
            ))
        ];

        $response = $this->getResponseSkeleton($originalParameters, $totalRows, $currentPage, $recordsPerPage);

        $queryBuilder = $specimenSearchQuery->build($parameters);


        $data = [];
        foreach ($this->specimenBatchProvider->iterate($queryBuilder, $offset, $recordsPerPage, 50, !$returnOnlyIds) as $specimen) {
            if ($returnOnlyIds) {
                $data[] = $specimen;
            } else {
                $data[] = $this->resolveSpecimen($specimen);
            }
        }
        $response['result'] = $data;

        return $response;
    }

    protected function getResponseSkeleton(array $originalParameters, int $totalRows, int $currentPage, int $recordsPerPage): array
    {
        // get the number of pages and check the active page again
        $lastPage = (int)ceil($totalRows / $recordsPerPage);
        if ($currentPage > $lastPage) {
            $currentPage = $lastPage;
        }

        //remove null values
        $originalParameters = array_filter(
            $originalParameters,
            static fn($value) => $value !== null
        );

        $newParameters = '&' . http_build_query($originalParameters, '', '&', PHP_QUERY_RFC3986);
        $url = $this->router->generate('services_rest_objects_specimens', [], UrlGeneratorInterface::ABSOLUTE_URL);

        return array('total' => $totalRows,
            'itemsPerPage' => $originalParameters['rpp'],
            'page' => $currentPage,
            'previousPage' => $url . '?p=' . (($currentPage > 0) ? ($currentPage - 1) : 1) . $newParameters,
            'nextPage' => $url . '?p=' . (($currentPage < $lastPage && $currentPage > 0) ? ($currentPage + 1) : $lastPage) . $newParameters,
            'firstPage' => $url . '?p=1' . $newParameters,
            'lastPage' => $url . '?p=' . $lastPage . $newParameters,
            'totalPages' => $lastPage,
            'result' => array()
        );
    }


    /**
     * get all or some properties of a specimen with given ID
     *
     * @param Specimens $specimen specimen
     * @param string $fieldGroups which groups should be returned (dc, dwc, jacq), defaults to all
     * @return array properties (dc, dwc and jacq)
     */
    public function resolveSpecimen(Specimens $specimen, string $fieldGroups = '', bool $removeEmptyValues = false): array
    {

        if (!str_contains($fieldGroups, "dc") && !str_contains($fieldGroups, "dwc") && !str_contains($fieldGroups, "jacq")) {
            $fieldGroups = "dc, dwc, jacq";
        }

        $ret = array();
        if (str_contains($fieldGroups, "dc")) {
            $ret['dc'] = $this->specimenService->getDublinCore($specimen);
        }
        if (str_contains($fieldGroups, "dwc")) {
            $ret['dwc'] = $this->specimenService->getDarwinCore($specimen);
        }
        if (str_contains($fieldGroups, "jacq")) {
            $ret['jacq'] = $this->specimenService->getJACQ($specimen);
        }

        if ($removeEmptyValues) {
            $resultsFiltered = [];
            foreach ($ret as $format => $group) {
                foreach ($group as $key => $value) {
                    if (!empty($value)) {
                        $resultsFiltered[$format][$key] = $value;
                    }
                }
            }
            return $resultsFiltered;
        }

        return $ret;
    }

}
