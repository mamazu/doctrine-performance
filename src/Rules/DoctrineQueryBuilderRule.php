<?php

declare(strict_types=1);

namespace Mamazu\DoctrinePerformance\Rules;

use Generator;
use Mamazu\DoctrinePerformance\Helper\GetEntityFromClassName;
use Mamazu\DoctrinePerformance\Helper\UnwrapValue;
use Mamazu\DoctrinePerformance\Services\MetadataService;
use PhpParser\Node;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\UnionType;
use PHPStan\Type\MixedType;
use PHPStan\Type\ThisType;
use PHPStan\Type\VerbosityLevel;

/**
 * @implements Rule<MethodCall>
 */
class DoctrineQueryBuilderRule implements Rule
{
	private const RULE_IDENTIFIER = 'doctrine.queryBuilder.performance';

	// Issued when the argument of the where expression is not a string constant
	private const RULE_IDENTIFIER_NOT_SUPPORTED = 'doctrine.queryBuilder.performance.nonStringWhere';

	// Issued when there is no way to identify the entity name from types
	private const RULE_IDENTIFIER_NO_ENTITY_FOUND = 'doctrine.queryBuilder.performance.noEntityFound';

	public function __construct(
		private GetEntityFromClassName $entityClassFinder,
		private MetadataService $metadataService
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
		if (! $node->name instanceof Node\Identifier || (stripos((string) $node->name, 'where')) === false) {
			return [];
		}

		// Get the type of the object the method is called on
		$calledOnType = $scope->getType($node->var);

		if ($calledOnType instanceof ThisType || $calledOnType instanceof MixedType || $calledOnType instanceof UnionType) {
			return [];
		}

		// Doctrine\ORM\QueryBuilder or Doctrine\DBAL\Query\QueryBuilder
		if ($calledOnType->isInstanceOf('Doctrine\ORM\QueryBuilder')->no() &&
			$calledOnType->isInstanceOf('Doctrine\DBAL\Query\QueryBuilder')
				->no()) {
			return [];
		}

		$aliasMap = $this->getAliasMap($node, $scope);
		if ($methodCall === null) {
			return [
				RuleErrorBuilder::message('Unable to find either `from` or `createQueryBuilder` call (so no idea what type of entity it is.')
					->identifier(self::RULE_IDENTIFIER_NO_ENTITY_FOUND)
					->build(),
			];
		}


		// Extract Expression from method call
		$queryString = $node->getArgs()[0]->value;
		if (!$queryString instanceof String_) {
			return [
				RuleErrorBuilder::message('Non constant strings in where method is not supported.')
					->identifier(self::RULE_IDENTIFIER_NOT_SUPPORTED)
					->build(),
			];
		}

		$error = [];
		foreach ($this->parseArgumentAndRetunUnindexedColumns($aliasMap, $queryString->value) as [$className, $property]) {
			$error[] = RuleErrorBuilder::message('Column not indexed: ' . $className . '::' . $property)
					->identifier(self::RULE_IDENTIFIER)
					->build()
			;
		}

		return $error;
	}

	private function getCallFromChain(Node $node, string $name): ?MethodCall
	{
		if ($node instanceof MethodCall) {
			if ((string) $node->name === $name) {
				return $node;
			}

			return $this->getCallFromChain($node->var, $name);
		}

		return null;
	}

	/** @return null|array{string, string} */
	private function getEntityClass(Scope $scope, MethodCall $methodCall): ?array {
		$entityArgument = $methodCall->getArgs()[0]->value;
		$className = UnwrapValue::className($entityArgument, $scope);

		if ($className === null) {
			return null;
		}

		$aliasArgument = $methodCall->getArgs()[1]->value;
		$alias = UnwrapValue::string($aliasArgument, $scope) ?? 'o';

		return [$alias => $className];
	}

	/**
	 * @param array<string, class-string> $aliasMapping
	 *
	 * @return Generator<class-string, string>
	*/
	private function parseArgumentAndRetunUnindexedColumns(array $aliasMapping, string $queryString): \Generator
	{
		$usedColumns = [];
		$parts = explode('=', $queryString);
		foreach ($parts as $part) {
			$part = trim($part);
			if (str_contains($part, '.')) {
				[$entity, $property] = explode('.', $part, 2);
				$usedColumns[$entity] = [...($usedColumns[$entity] ?? []), $property];
			}
		}

		$unusedColumns = [];
		foreach ($usedColumns as $alias => $fields) {
			if (!array_key_exists($alias, $aliasMapping)) {
				continue;
				// This should not happen.
				throw new \InvalidArgumentException(sprintf(
					'No alias defined in map for "%s" known aliases: %s',
					$alias,
					implode(',', array_keys($aliasMapping))
				));
			}

			$className = $aliasMapping[$alias];
			if ($this->metadataService->shouldEntityBeSkipped($className)) {
				continue;
			}

			foreach ($this->metadataService->nonIndexedColums($className, $fields) as $nonIndexedColumn) {
				yield $className => $nonIndexedColumn;
			}
		}
	}

	private function getAliasMap(Node $currentNode, Scope $scope): ?array {
		// Searching for the ->from() call to get the entity class name
		$methodCall = $this->getCallFromChain($currentNode->var, 'from');
		if ($methodCall instanceof MethodCall) {
			return $this->getEntityClass($scope, $methodCall);
		}

		$methodCall = $this->getCallFromChain($currentNode->var, 'createQueryBuilder');
		if ($methodCall instanceof MethodCall) {
			$aliasName = UnwrapValue::string($methodCall->getArgs()[0]->value, $scope);

			$left = $scope->getType($methodCall->var);
			if ($left instanceof ThisType) {
				$entityType = $this->entityClassFinder->getEntityClassName($left->getStaticObjectType());
				if ($entityType instanceof ObjectType) {
					$className = $type->getClassName();
				}
				return null;
			} else if ($left instanceof PropertyFetch) {
				$className = $scope->getType($left);
			} else {
				return null;
			}

			return [$aliasName => $className];
		}

		return null;
	}
}
