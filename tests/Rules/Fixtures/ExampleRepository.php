<?php

declare(strict_types=1);

namespace Test\Mamazu\DoctrinePerformance\Rules\Fixtures;

use Test\Mamazu\DoctrinePerformance\Rules\Fixtures\Entities\Settings;
use Doctrine\Persistence\ObjectRepository;

class ExampleRepository
{
    /**
     * @param ObjectRepository<Settings> $repository
     */
    public function __construct(
		private ObjectRepository $repository
	) {
	}

	public function getSettings(): Settings
	{
		return $this->repository->findBy([
			'name1' => 'on',
			'name2' => 'on',
			'name3' => 'on',
			'name4' => 'on',
			'name5' => 'on',
			'name6' => 'on',
			'name7' => 'on',
			'name8' => 'on',
		]);
	}
}
