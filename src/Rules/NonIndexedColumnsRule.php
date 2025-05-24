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

/** @phpstan-type NonIndexedColumnData array{entityClass: string, properties: array<string>, lineNumber: int} */
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
					$entityClass = $error[0];
					if ($this->metadataService->shouldEntityBeSkipped($entityClass)) {
						continue;
					}

					$fields = $error[1];

					$unindexedFields = $this->metadataService->nonIndexedColums($entityClass, $fields);
					foreach ($unindexedFields as $field) {
						$errors[] = RuleErrorBuilder::message(sprintf(
							'Found column "%s" of entity "%s" which is not indexed.',
							$field,
							$entityClass,
						))
							//->identifier()
							->file($file)
							->line($error[2])
							->build()
						;
					}
				}
			}
		}
		return $errors;
	}
}
