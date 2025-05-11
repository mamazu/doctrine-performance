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
			$this->entityManager = require $this->path;
		}

		return $this->entityManager;
	}
}
