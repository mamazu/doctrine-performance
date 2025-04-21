<?php

declare(strict_types=1);

namespace Mamazu\DoctrinePerformance\Services;

use Mamazu\DoctrinePerformance\Attributes\SmallTable;
use ReflectionClass;

class MetadataService
{
	public function isColumnIndexed(string $className, string $columnName): bool
	{
		return false;
	}

	public function shouldEntityBeSkipped(string|ReflectionClass $className): bool
	{
		if ($className instanceof ReflectionClass) {
			$reflection = $className;
		} else {
			$reflection = new ReflectionClass($className);
		}

		return count($reflection->getAttributes(SmallTable::class)) > 0;
	}
}
