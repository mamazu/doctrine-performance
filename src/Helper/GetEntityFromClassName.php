<?php

declare(strict_types=1);

namespace Mamazu\DoctrinePerformance\Helper;

use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Type\Generic\GenericObjectType;
use PHPStan\Type\ObjectType;
use Doctrine\Persistence\ObjectRepository;

class GetEntityFromClassName
{
	public function __construct(
		private ReflectionProvider $reflectionProvider,
	) {}

	public function getEntityClassName($repositoryType): ?ObjectType
	{
		if ($repositoryType instanceof GenericObjectType) {
			$entityType = $repositoryType->getTypes()[0];
			if (!$entityType instanceof ObjectType) {
				return null;
			}

			return $entityType;
		}


		$repositoryReflection = $this->reflectionProvider
			->getClass($repositoryType->getClassName())
			->getAncestorWithClassName(ObjectRepository::class);

		if ($repositoryReflection === null) {
			return null;
		}

		$type = $repositoryReflection
			->getActiveTemplateTypeMap()
			->getType('TEntityClass')
		;

		if (! $type instanceof ObjectType) {
			return null;
		}
		return $type;
	}
}
