<?php

declare(strict_types=1);

namespace Mamazu\DoctrinePerformance\Helper;

use Doctrine\Persistence\ObjectRepository;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Type\Generic\GenericObjectType;
use PHPStan\Type\Generic\TemplateTypeMap;
use PHPStan\Type\ObjectType;

class GetEntityFromClassName
{
	public function __construct(
		private ReflectionProvider $reflectionProvider,
	) {}

	public function getEntityClassName($repositoryType): ?ObjectType
	{
		if ($repositoryType instanceof GenericObjectType) {
			$entityType = $repositoryType->getTypes()[0];
			if (! $entityType instanceof ObjectType) {
				return null;
			}

			return $entityType;
		}

		$genericReflection = $this->reflectionProvider
			->getClass($repositoryType->getClassName())
			->getAncestorWithClassName(ObjectRepository::class);

		if ($genericReflection === null) {
			return null;
		}

		return $this->getTemplateArgument($genericReflection->getActiveTemplateTypeMap());
	}

	private function getTemplateArgument(TemplateTypeMap $typeMap) {
		$type = reset($typeMap->getTypes());
		if ($type instanceof ObjectType) {
			return $type;
		}

		return null;
	}
}
