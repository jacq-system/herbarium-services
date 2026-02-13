<?php declare(strict_types=1);

namespace App\Facade;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use JACQ\Entity\Jacq\Herbarinput\Specimens;
use JACQ\Service\SpeciesService;
use JACQ\Service\SpecimenService;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

readonly class ObjectsFacade
{
    public function __construct(protected EntityManagerInterface $entityManager, protected RouterInterface $router, protected SpeciesService $taxonService, protected SpecimenService $specimenService)
    {
    }

    /**
     * search for all specimens which fit given criteria
     * possible taxon-IDs have to be given as a list, as the search service for taxons need a special key and only the main rest-function has this key
     * params are all optional
     *      p (page to display, default first page),
     *      rpp (records per page, default 50),
     *      list (return just a list of specimen-IDs, default 1),
     *      term (search for taxon)
     *      herbnr (search for herbarium nuber)
     *      collnr (search for collection number))
     *      sc (search for source code)
     *      cltr (search for collector)
     *      nation (search for nation)
     *      type (type records only, default 0)
     *      withImages (records with images only, default 0)
     *      sort (sort order, default sciname, herbnr)
     *
     * @param array $params any parameters of the search
     * @param array $taxonIDList search for taxon terms has already finished, this is the list of results; defaults to empty array
     * @return array
     */
    public function resolveSpecimens(int $p, int $rpp, int $listOnly, string $term, string $sc, string $coll, int $type, string $sort, string $herbnr, string $nation, int $withImages, string $cltr, bool $onlyAccessibleSpecimens = true): array
    {
        $taxonIDList = [];
        if (!empty($term)) {
            $names = explode(',', $term);
            foreach ($names as $name) {
                $taxa = $this->taxonService->fulltextSearch($name);

                foreach ($taxa as $taxon) {
                    $taxonIDList[] = $taxon['taxonID'];
                }
            }
            if (count($taxonIDList) === 0) {
                return [];
            }
        }
        $dataQueryBuilder = $this->getQueryBuilder($taxonIDList, $herbnr, $sc, $cltr, $nation, $type, $withImages, $sort, $cltr, $onlyAccessibleSpecimens)
            ->setFirstResult($rpp * $p)
            ->setMaxResults($rpp);

        $list = $dataQueryBuilder->getQuery()->getResult();

        $countQueryBuilder = $this->getQueryBuilder($taxonIDList, $herbnr, $sc, $cltr, $nation, $type, $withImages, $sort, $cltr, $onlyAccessibleSpecimens)
            ->resetDQLPart('orderBy')
            ->select('COUNT(DISTINCT s.id)');
        $nrRows = (int)$countQueryBuilder->getQuery()->getSingleScalarResult();

        // get the number of pages and check the active page again
        $lastPage = (int) floor(($nrRows - 1) / $rpp);
        if ($p > $lastPage) {   // if the page number was wrongly set to a too large value
            $p = $lastPage + 1; // reset it to the page after the last page
        }

        $originalParameters = [
            'p' => $p,               // page, default: display first page
            'rpp' => $rpp,              // records per page, default: 50
            'list' => $listOnly,               // return just a list of specimen-IDs?, default: yes
            'term' => $term,              // search for scientific name (joker = *)
            'herbnr' => $herbnr,              // search for herbarium number (joker = *)
            'collnr' => $coll,              // search for collection number (joker = *)
            'sc' => $sc,              // search for a source-code
            'cltr' => $cltr,              // search for a collector
            'nation' => $nation,              // search for a nation
            'type' => $type,               // switch, search only for type records (default: no)
            'withImages' => $withImages,               // switch, search only for records with images (default: no)
            'sort' => $sort            // sorting of result, default: order scinames and herbnumbers
        ];

        $newParameters = '&' . http_build_query($originalParameters, '', '&', PHP_QUERY_RFC3986);
        $url = $this->router->generate('services_rest_objects_specimens', [], UrlGeneratorInterface::ABSOLUTE_URL);

        $data = array('total' => $nrRows,
            'itemsPerPage' => $originalParameters['rpp'],
            'page' => $p + 1,
            'previousPage' => $url . '?p=' . (($p > 0) ? ($p - 1) : 0) . $newParameters,
            'nextPage' => $url . '?p=' . (($p < $lastPage) ? ($p + 1) : $lastPage) . $newParameters,
            'firstPage' => $url . '?p=0' . $newParameters,
            'lastPage' => $url . '?p=' . $lastPage . $newParameters,
            'totalPages' => $lastPage + 1,
            'result' => array()
        );
        foreach ($list as $specimen) {
            $data['result'][] = (!empty($listOnly)) ? $specimen->id : $this->resolveSpecimen($specimen);
        }

        return $data;
    }

    protected function getQueryBuilder(array $taxonIDList, string $herbNumber, string $institutionCode, string $collectorName, string $nation, int $typus, int $withImages, string $sort, string $collectionNr, bool $onlyAccessibleSpecimens = true): QueryBuilder
    {
        $qb = $this->entityManager->getRepository(Specimens::class)->createQueryBuilder('s');
        $joins = [];

        if ($onlyAccessibleSpecimens){
            $qb->select('s')
                ->where('s.accessibleForPublic = true');
        }

        if (count($taxonIDList) > 0) {
            $qb->leftJoin('s.species', 'species')
                ->andWhere('species.id IN (:taxonIDList)')
                ->setParameter('taxonIDList', $taxonIDList, ArrayParameterType::INTEGER);
        }

        if (!empty($herbNumber)) {
            if (str_contains($herbNumber, '*')) {
                $qb->andWhere('s.herbNumber LIKE :herbNr')
                    ->setParameter('herbNr', strtr($herbNumber, '*', '%'));
            } else {
                $qb->andWhere('s.herbNumber LIKE :herbNrPure')
                    ->setParameter('herbNrPure', $herbNumber);
            }
        }
        if (!empty($collectionNr)) {
            if (str_contains($collectionNr, '*')) {
                $qb->andWhere('s.collectionNumber LIKE :collNr')
                    ->setParameter('collNr', strtr($collectionNr, '*', '%'));
            } else {
                $qb->andWhere('s.collectionNumber = :collNrPure')
                    ->setParameter('collNrPure', $collectionNr);
            }
        }
        if (!empty($institutionCode)) {
            $joins[] = 'herbCollection';
            $joins[] = 'institution';
            $qb->leftJoin('s.herbCollection', 'herbCollection')
                ->leftJoin('herbCollection.institution', 'institution')
                ->andWhere('institution.code = :sc')
                ->setParameter('sc', $institutionCode);
        }
        if (!empty($collectorName)) {
            $joins[] = 'collector';
            $qb->leftJoin('s.collector', 'collector')
                ->leftJoin('s.collector2', 'collector2')
                ->andWhere('collector.name LIKE :cltr')
                ->andWhere('collector2.name LIKE :cltrBothSide')
                ->setParameter('cltr', $collectorName . "%")
                ->setParameter('cltrBothSide', "%" . $collectorName . "%");
        }
        if (!empty($nation)) {
            $joins[] = 'country';
            $qb->leftJoin('s.country', 'country')
                ->andWhere($qb->expr()->orX(
                    $qb->expr()->eq('country.name', ':nation'),
                    $qb->expr()->eq('country.nameEng', ':nation'),
                    $qb->expr()->eq('country.nameDe', ':nation'),

                ))
                ->setParameter('nation', $nation);
        }
        if (!empty($typus)) {
            $joins[] = 'typus';
            $qb->innerJoin('s.typus', 'typus');
        }
        if (!empty($withImages)) {
            $qb->andWhere($qb->expr()->orX(
                $qb->expr()->eq('s.image', '1'),
                $qb->expr()->eq('s.imageObservation', '1')
            ));
        }
        return $this->sortQuery($qb, $sort, $joins);
    }

    protected function sortQuery(QueryBuilder $queryBuilder, string $sort, array $joins): QueryBuilder
    {
        $parts = explode(',', $sort);
        foreach ($parts as $part) {
            $t_part = trim($part);
            if (str_starts_with($t_part, '-')) {
                $key = substr($t_part, 1);
                $orderSequence = " DESC";
            } elseif (str_starts_with($t_part, '+')) {
                $key = substr($t_part, 1);
                $orderSequence = " ASC";
            } else {
                $key = $t_part;
                $orderSequence = '';
            }
            switch ($key) {
                // TODO this sort is off, as the database is crazy. tbl_tax_sciname should be only two columns in tbl_tax_species but is as separate table which is unpossible to model with ORM. When fixed, also the route anotations shoudl be changed (include example and default by sciname sort e.g.)
//                case 'sciname':
//                    if(empty($joins['unavailable'])) {
//                        $queryBuilder->join('s.xx', 'species');
//                    }
//                        $queryBuilder->addOrderBy('species.scientificName', $orderSequence);
//                    break;
                case 'cltr':
                    if (empty($joins['collector'])) {
                        $queryBuilder->leftJoin('s.collector', 'collector')
                            ->leftJoin('s.collector2', 'collector2');
                    }
                    $queryBuilder
                        ->addOrderBy('collector.name', $orderSequence)
                        ->addOrderBy('collector2.name', $orderSequence);
                    break;
                case 'ser':
                    $queryBuilder->leftJoin('s.series', 'series')
                        ->addOrderBy('series.name', $orderSequence);
                    break;
                case 'num':
                    $queryBuilder
                        ->addOrderBy('s.number', $orderSequence);
                    break;
                case 'herbnr':
                    $queryBuilder
                        ->addOrderBy('s.herbNumber', $orderSequence);
                    break;
            }
        }

        return $queryBuilder->addOrderBy('s.id');
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
