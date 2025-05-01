<?php

declare(strict_types=1);

namespace Mamazu\DoctrinePerformance\Rules;

use Mamazu\DoctrinePerformance\Services\MetadataService;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\ArrayItem;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\Generic\GenericObjectType;
use PHPStan\Type\VerbosityLevel;
/**
 * @implements Rule<MethodCall>
 */
class DoctrineRepositoryRule implements Rule
{
	private const RULE_IDENTIFIER = 'doctrine.repository.performance';

	public function __construct(
		private MetadataService $metadataService,
	) {}

	public function getNodeType(): string
	{
		return MethodCall::class;
	}

	/**
	 * @param MethodCall $node
	 */
	public function processNode(Node $node, Scope $scope): array
	{
		if (! $node->name instanceof Node\Identifier || (stripos((string) $node->name, 'findBy')) === false) {
			return [];
		}

		$repositoryType=$scope->getType($node->var);
		if ($repositoryType->isInstanceOf('Doctrine\Persistence\ObjectRepository')->no()) {
			return [];
		}

		// Checking for Repository vs Repository<Entity>
		if (!$repositoryType instanceof GenericObjectType) {
			return [
				RuleErrorBuilder::message('Found ObjectRepository but could not determine type of its entity')
					->tip('Use something like /** @var ObjectRepository<Entity> */ to denote the entity of the repository')
					->build(),
			];
		}

		$entityClass = $repositoryType->getTypes()[0]->getClassName();
		if ($this->metadataService->shouldEntityBeSkipped($entityClass)) {
			return [];
		}

		$usedColumns = $this->getUsedColumns($node->args);
		$notIndexedColumns = $this->metadataService->nonIndexedColums($entityClass, array_keys($usedColumns));

		$errors = [];
		foreach ($notIndexedColumns as $notIndexedColumn){
			$token = $usedColumns[$notIndexedColumn];

			$errors[] = RuleErrorBuilder::message(sprintf(
				'Found column "%s" of entity "%s" which is not indexed.',
				$notIndexedColumn,
				$entityClass,
			))
				->line($token->getLine())
				->build()
			;

		}
		return $errors;
	}

	/**
	 * @param array<Arg> $args
	 *
	 * @return array<string, ArrayItem>
	 */
	private function getUsedColumns(array $args): array
	{
		$columns = [];
		foreach ($args as $arg) {
			$value = $arg->value;
			if (!($value instanceof Array_)) {
				continue;
			}

			foreach ($value->items as $item) {
				$columns[$item->key->value] = $item;
			}
		}

		return $columns;
	}
}
