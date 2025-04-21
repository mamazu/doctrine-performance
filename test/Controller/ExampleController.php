<?php

declare(strict_types=1);

namespace Test\Mamazu\DoctrinePerformance\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Test\Mamazu\DoctrinePerformance\Entities\Books;

class ExampleController
{
    public function __construct(
        private EntityManagerInterface $entityManager
    )
    {
    }

    public function __invoke(): void
    {
        $qb = $this->entityManager->createQueryBuilder();

        $qb
            ->from(Books::class, 'u')
            ->where('u.name')
        ;

        $qb
            ->from(Books::class, 'u')
            ->andWhere('u.name')
            ->orWhere('u.name')
        ;
    }
}
