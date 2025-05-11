<?php

declare(strict_types=1);

namespace Mamazu\DoctrinePerformance\Services;

use Doctrine\ORM\EntityManagerInterface;

interface EntityManagerLoaderInterface
{
    public function getEntityManager(): EntityManagerInterface;
}
