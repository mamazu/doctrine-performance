<?php

declare(strict_types=1);

namespace Mamazu\DoctrinePerformance\Rules;

use Generator;
use InvalidArgumentException;
use Mamazu\DoctrinePerformance\Collectors\DoctrineQueryBuilderCollector;
use Mamazu\DoctrinePerformance\Collectors\DoctrineRepositoryCollector;
use Mamazu\DoctrinePerformance\Services\MetadataService;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\CollectedDataNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @phpstan-type NonIndexedColumnData array{entityClass: string, properties: array<string>, lineNumber: int}|array{message: string, identifier: string, tip: ?string, lineNumber: int}
 *
 * @implements Rule<CollectedDataNode>
 */
class NonIndexedColumnsRule implements Rule
{
	// Issued when there is no way to identify the entity name from types
	public const RULE_IDENTIFIER = 'doctrine.queryBuilder.performance.nonIndexedColum';

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
		foreach ($this->getErrorList($node) as $file => $errorData) {
			if (array_key_exists('message', $errorData)) {
				$this->processRaw($errors, $errorData, $file);
			} else if (array_key_exists('entityClass', $errorData)) {
				$this->processDeclaration($errors, $errorData, $file);
			} else {
				throw new InvalidArgumentException('Invalid array keys: ' . implode(',', array_keys($errorData)));
			}
		}

		return $errors;
	}

	private function getErrorList(Node $node): Generator
	{
		foreach ($node->get(DoctrineQueryBuilderCollector::class) as $file => $declarations) {
			foreach ($declarations as $declaration) {
				foreach ($declaration as $errorData) {
					yield $file => $errorData;
				}
			}
		}

		foreach ($node->get(DoctrineRepositoryCollector::class) as $file => $declarations) {
			foreach ($declarations as $declaration) {
				foreach ($declaration as $errorData) {
					yield $file => $errorData;
				}
			}
		}
	}

	/**
	 * @param array<RuleError> $errors
	 * @param NonIndexedColumnData $declaration
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
				->identifier(self::RULE_IDENTIFIER)
				->file($file)
				->line($declaration['lineNumber'])
				->build()
			;
		}
	}

	private function processRaw(array &$errors, array $errorData, string $file): void
	{
		$error = RuleErrorBuilder::message($errorData['message'])
			->file($file)
			->identifier($errorData['identifier'])
			->line($errorData['line'])
		;

		if (array_key_exists('tip', $errorData)) {
			$error = $error->tip($errorData['tip']);
		}

		$errors[] = $error->build();
	}
}
