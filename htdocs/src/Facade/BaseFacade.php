<?php

declare(strict_types=1);

namespace App\Facade;

use Doctrine\DBAL\Result;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\RouterInterface;

abstract readonly class BaseFacade
{
    public function __construct(protected EntityManagerInterface $entityManager, protected RouterInterface $router) {}

    /**
     * @param mixed[] $params
     * @param mixed[] $types
     */
    protected function query(string $sql, array $params = [], array $types = []): Result
    {
        return $this->entityManager->getConnection()->executeQuery($sql, $params, $types);
    }
}
