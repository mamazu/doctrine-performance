<?php

declare(strict_types=1);

namespace Test\Mamazu\DoctrinePerformance\Collectors\Fixtures;

use Doctrine\ORM\EntityManagerInterface;
use Test\Mamazu\DoctrinePerformance\Collectors\Fixtures\Entities\Books;
use Test\Mamazu\DoctrinePerformance\Collectors\Fixtures\Entities\Settings;
use Doctrine\ORM\EntityRepository;

class UsingQueryBuilder
{
	public function __construct(
		private EntityManagerInterface $entityManager
	) {
	}

	public function __invoke(): void
	{
		$qb = $this->entityManager->createQueryBuilder();

		$qb
			->from(Books::class, 'u')
			->where('u.title')
			->andWhere('u.title')
			->orWhere('u.title')
		;

		$qb
			->from(Settings::class, 's')
			->andWhere('s.key')
		;

		$qb
			->from(Books::class, 'b')
			->where('b.author')
		;
	}
}
