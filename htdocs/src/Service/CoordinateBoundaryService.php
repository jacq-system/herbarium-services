<?php declare(strict_types=1);

namespace App\Service;

use JACQ\Entity\Jacq\Herbarinput\GeoNationBoundaries;
use JACQ\Entity\Jacq\Herbarinput\GeoProvinceBoundaries;
use Doctrine\ORM\EntityManagerInterface;


readonly class CoordinateBoundaryService
{

    public function __construct(protected EntityManagerInterface $entityManager)
    {
    }

    /**
     * check a given coordinate with all known boundaries of a given nation
     *
     * @param int $nationID ID of nation
     * @param float $lat latitude
     * @param float $lon longitude
     * @return array nr of checked boundaries and true if inside, false if outside and null if not checked
     */
    public function nationBoundaries(int $nationID, float $lat, float $lon): array
    {
        $qb = $this->entityManager->getRepository(GeoNationBoundaries::class)->createQueryBuilder('b')
            ->select('b')
            ->where('b.nationId = :nationID')
            ->setParameter('nationID', $nationID);
        $boundaries = $qb->getQuery()->getResult();

        return array("nrBoundaries" => count($boundaries),
            "inside"       => $this->checkBoundingBox($lat, $lon, $boundaries));
    }


    /**
     * check a given coordinate with all known boundaries of a given province
     *
     * @param int $provinceID ÍD of province
     * @param float $lat latitude
     * @param float $lon longitude
     * @return array nr of checked boundaries and true if inside, false if outside and null if not checked     */
    public function provinceBoundaries(int $provinceID, float $lat, float $lon): array
    {
        $qb = $this->entityManager->getRepository(GeoProvinceBoundaries::class)->createQueryBuilder('b')
            ->select('b')
            ->where('b.provinceId = :provinceID')
            ->setParameter('provinceID', $provinceID);
        $boundaries = $qb->getQuery()->getResult();

        return array("nrBoundaries" => count($boundaries),
            "inside"       => $this->checkBoundingBox($lat, $lon, $boundaries));
    }


    /**
     * check a list of bounding boxes if coords lie inside
     *
     * @param float $lat latitude
     * @param float $lon longitude
     * @param GeoNationBoundaries[] | GeoProvinceBoundaries[] $boundaries list of boundaries (if any)
     * @return bool|null true if inside, false if outside, null if list of boundaries is empty
     */
    protected function checkBoundingBox(float $lat, float $lon, array $boundaries): ?bool
    {
        if (!empty($boundaries)) {
            foreach ($boundaries as $boundary) {
                if ($lat >= $boundary->boundSouth && $lat <= $boundary->boundNorth
                    && ((($boundary->boundEast > $boundary->boundWest) && ($lon >= $boundary->boundWest && $lon <= $boundary->boundEast))
                        || ($boundary->boundEast < $boundary->boundWest && ($lon >= $boundary->boundWest || $lon <= $boundary->boundEast)))) {
                    return true;
                }
            }
            return false;
        } else {
            return null;
        }
    }
}
