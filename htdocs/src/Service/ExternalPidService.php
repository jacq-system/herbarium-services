<?php

declare(strict_types=1);

namespace App\Service;

use JACQ\Entity\Jacq\Herbarinput\Specimens;

class ExternalPidService
{
    /**
     * @return ?mixed[]
     */
    public function getAll(Specimens $specimen): ?array
    {
        $result = array_values(array_filter([
            $this->getGbifAsArray($specimen),
            $this->getDisscoAsArray($specimen),
        ]));

        return [] === $result ? null : $result;
    }

    /**
     * @return ?mixed[]
     */
    public function getGbifAsArray(Specimens $specimen): ?array
    {
        if (empty($specimen->pidGbif)) {
            return null;
        }

        return [
            'stableIdentifier' => $specimen->pidGbif,
            'link' => $specimen->pidGbif,
            'identifierIssuer' => 'GBIF',
            'visible' => true,
        ];
    }

    /**
     * @return ?mixed[]
     */
    public function getDisscoAsArray(Specimens $specimen): ?array
    {
        if (empty($specimen->pidDissco)) {
            return null;
        }

        return [
            'stableIdentifier' => $specimen->pidDissco,
            'link' => $specimen->pidDissco,
            'identifierIssuer' => 'DiSSCo',
            'visible' => true,
        ];
    }
}
