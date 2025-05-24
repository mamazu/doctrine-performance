<?php

declare(strict_types=1);

namespace Mamazu\DoctrinePerformance\Rules;

use Mamazu\DoctrinePerformance\Collectors\DoctrineQueryBuilderCollector;
use Mamazu\DoctrinePerformance\Services\MetadataService;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\CollectedDataNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Rules\RuleError;

/**
 * @phpstan-type NonIndexedColumnData array{entityClass: string, properties: array<string>, lineNumber: int}
 *
 * @implements Rule<CollectedDataNode>
*/
class NonIndexedColumnsRule implements Rule
{
	public function __construct(
		private readonly MetadataService $metadataService
	) {}

	public function getNodeType(): string
	{
		return CollectedDataNode::class;
	}

	public function processNode(Node $node, Scope $scope): array
	{
		$errors = [];
		foreach ($node->get(DoctrineQueryBuilderCollector::class) as $file => $declarations) {
			foreach ($declarations as $declaration) {
				foreach ($declaration as $error) {
					$this->processDeclaration($errors, $error, $file);
				}
			}
		}

		return $errors;
	}

	/**
	 * @param array<RuleError> $errors
	 * @param NonIndexedColumnData $declaration
	 * @param string $file
	*/
	private function processDeclaration(array &$errors, array $declaration, string $file): void
	{
		$entityClass = $declaration['entityClass'];
		if ($this->metadataService->shouldEntityBeSkipped($entityClass)) {
			return;
		}

		$fields = $declaration['properties'];

		$unindexedFields = $this->metadataService->nonIndexedColums($entityClass, $fields);
		foreach ($unindexedFields as $field) {
			$errors[] = RuleErrorBuilder::message(sprintf(
				'Found column "%s" of entity "%s" which is not indexed.',
				$field,
				$entityClass,
			))
				//->identifier()
				->file($file)
				->line($declaration['lineNumber'])
				->build()
			;
		}
	}
}
