<?php

declare(strict_types=1);

namespace Test\Mamazu\DoctrinePerformance\Rules\Fixtures;

use Doctrine\ORM\EntityManagerInterface;
use Test\Mamazu\DoctrinePerformance\Rules\Fixtures\Entities\Books;
use Test\Mamazu\DoctrinePerformance\Rules\Fixtures\Entities\Settings;

class ExampleController
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
			->where('u.name')
		;

		$qb
			->from(Books::class, 'u')
			->andWhere('u.name')
			->orWhere('u.name')
		;

		$qb
			->from(Settings::class, 's')
			->andWhere('s.name')
		;
	}
}
