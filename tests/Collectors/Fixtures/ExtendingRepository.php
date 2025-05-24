<?php

declare(strict_types=1);

namespace Test\Mamazu\DoctrinePerformance\Collectors\Fixtures;

use Doctrine\ORM\EntityRepository;
use Test\Mamazu\DoctrinePerformance\Collectors\Fixtures\Entities\Books;

/**
 * @extends EntityRepository<Books>
 */
class ExtendingRepository extends EntityRepository
{
	public function findSomething(): void
	{
		$this->createQueryBuilder('b')
			->where('b.nonIndexed');
	}
}
