<?php

declare(strict_types=1);

namespace Mamazu\DoctrinePerformance\Services;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\Mapping\MappingException;
use Mamazu\DoctrinePerformance\Attributes\SmallTable;
use ReflectionClass;

class MetadataService
{
	private EntityManagerInterface $entityManager;

	public function __construct(EntityManagerLoader $loader) {
		$this->entityManager = $loader->getEntityManager();
	}

	/**
	 * @param array<string> $columnNames
	 *		The names of the columns that should be checked if they're indexed.
	 * @return array<string>
	 *		The list of columns that do not have an index to help.
	 */
	public function nonIndexedColums(string $className, array $columnNames): array
	{
		try {
			$classMetaData = $this->entityManager->getClassMetadata($className);
		} catch (MappingException) {
			return [];
		}

		sort($columnNames);

		// Check if the column is an indexed field
		$notIndexed = [];
		foreach ($columnNames as $columnName) {
			// Associations are always indexed
			if ($classMetaData->hasAssociation($columnName)) {
				continue;
			}

			$fieldData = $classMetaData->getFieldMapping($columnName);

			if($classMetaData->isUniqueField($columnName) || $classMetaData->isIdentifier($columnName)) {
				continue;
			}

			$notIndexed[] = $columnName;
		}

		if ($notIndexed === []){
			return [];
		}

		// Check if it's a composite index
		$indexes = [...($classMetaData->table['indexes'] ?? []), ...($classMetaData->table['uniqueConstraints'] ?? [])];
		foreach($indexes as ['columns' => $columns]) {
			// If all columns are in a composite index
			if ([] === array_diff($columns, $columnNames)) {
				return [];
			}
		}

		return $notIndexed;
	}

	/**
	 * @param class-string|ReflectionClass<object> $className
	 */
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
