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
			->where('u.title')
			->andWhere('u.title')
			->orWhere('u.title')
		;

		// This should complain because there is no mapping on that entity for the title field
		$qb
			->from(Settings::class, 's')
			->andWhere('s.title')
		;
	}
}
