<?php

declare(strict_types=1);

namespace Mamazu\DoctrinePerformance\Services;

use Doctrine\ORM\EntityManagerInterface;

class EntityManagerLoader implements EntityManagerLoaderInterface
{
	private EntityManagerInterface $entityManager;

	public function __construct(
		private ?string $path
	) {
	}

	public function getEntityManager(): EntityManagerInterface {
		if (! isset($this->entityManager)) {
			$entityManager = require $this->path;
			assert($entityManager instanceof EntityManagerInterface);
			$this->entityManager = $entityManager;
		}

		return $this->entityManager;
	}
}
