<?php

declare(strict_types=1);

namespace Mamazu\DoctrinePerformance\Helper;

use Doctrine\Persistence\ObjectRepository;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Type\Generic\GenericObjectType;
use PHPStan\Type\Generic\TemplateTypeMap;
use PHPStan\Type\ObjectType as NonGenericObjectType;

class GetEntityFromClassName
{
	public function __construct(
		private ReflectionProvider $reflectionProvider,
	) {}

	public function getEntityClassName($repositoryType): ?NonGenericObjectType
	{
		if ($repositoryType instanceof GenericObjectType) {
			$entityType = $repositoryType->getTypes()[0];
			if ($entityType instanceof NonGenericObjectType) {
				return $entityType;
			}

			return null;
		}

		$genericReflection = $this->reflectionProvider
			->getClass($repositoryType->getClassName())
			->getAncestorWithClassName(ObjectRepository::class);
		if ($genericReflection === null) {
			return null;
		}

		return $this->getTemplateArgument($genericReflection->getActiveTemplateTypeMap());
	}

	private function getTemplateArgument(TemplateTypeMap $typeMap): ?NonGenericObjectType {
		$type = reset($typeMap->getTypes());
		if ($type instanceof NonGenericObjectType) {
			return $type;
		}

		return null;
	}
}
