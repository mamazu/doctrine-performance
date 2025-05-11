<?php

declare(strict_types=1);

namespace Mamazu\DoctrinePerformance\Rules;

use Generator;
use Mamazu\DoctrinePerformance\Errors\ErrorMessage;
use Mamazu\DoctrinePerformance\Helper\AliasMap;
use Mamazu\DoctrinePerformance\Helper\GetEntityFromClassName;
use Mamazu\DoctrinePerformance\Helper\Result;
use Mamazu\DoctrinePerformance\Helper\UnwrapValue;
use Mamazu\DoctrinePerformance\Services\MetadataService;
use PhpParser\Node;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\UnionType;
use PHPStan\Type\MixedType;
use PHPStan\Type\ThisType;
use PHPStan\Type\VerbosityLevel;
use PHPStan\Type\ObjectType;

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

		try {
			$aliasMap = $this->getAliasMap($node, $scope);
		} catch (ErrorMessage $error) {
			return [
				RuleErrorBuilder::message($error->getMessage())
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

	private function getEntityClass(AliasMap $aliasMap, Scope $scope, MethodCall $methodCall): AliasMap {
		$entityArgument = $methodCall->getArgs()[0]->value;
		if ($entityArgument instanceof PropertyFetch) {
			if (((string) $entityArgument->getName) === '_entityName') {
				$entityType = $this->entityClassFinder->getEntityClassName($entityArgument->getStaticObjectType());
				if ($entityType instanceof ObjectType) {
					$className = $entityType->getClassName();
				}
			} else {
				throw new ErrorMessage('Using a variable as property access. That is too generic.');
			}
		} else {
			$className = UnwrapValue::className($entityArgument, $scope);
			if ($className === null) {
				throw new ErrorMessage('Unable to process argument');
			}
		}

		$aliasArgument = $methodCall->getArgs()[1]->value;
		$alias = UnwrapValue::string($aliasArgument, $scope) ?? 'o';

		$aliasMap->addAlias($alias, $className);
		return $aliasMap;
	}

	/**
	 * @return Generator<class-string, string>
	*/
	private function parseArgumentAndRetunUnindexedColumns(AliasMap $aliasMapping, string $queryString): \Generator
	{
		$usedColumns = [];
		$parts = explode('=', $queryString);
		foreach ($parts as $part) {
			$part = trim($part);
			if (str_contains($part, '.')) {
				[$entity, $right] = explode('.', $part, 2);

				$pos = strpos($right, ' ');
				if ($pos !== false) {
					$property = substr($right, 0, $pos);
				} else {
					$property = $right;
				}

				$usedColumns[$entity] = [...($usedColumns[$entity] ?? []), $property];
			}
		}

		$unusedColumns = [];
		foreach ($usedColumns as $alias => $fields) {
			if (!$aliasMapping->has($alias)) {
				continue;
				// This should not happen.
				throw new \InvalidArgumentException(sprintf(
					'No alias defined in map for "%s" known aliases: %s',
					$alias,
					implode(',', $aliasMapping->getAliases())
				));
			}

			$className = $aliasMapping->getAlias($alias);
			if ($this->metadataService->shouldEntityBeSkipped($className)) {
				continue;
			}

			foreach ($this->metadataService->nonIndexedColums($className, $fields) as $nonIndexedColumn) {
				yield $className => $nonIndexedColumn;
			}
		}
	}

	private function getAliasMap(Node $currentNode, Scope $scope): AliasMap {
		$aliasMap = new AliasMap();

		// Searching for the ->from() call to get the entity class name
		$methodCall = $this->getCallFromChain($currentNode->var, 'from');
		if ($methodCall instanceof MethodCall) {
			return $this->getEntityClass($aliasMap, $scope, $methodCall);
		}

		$methodCall = $this->getCallFromChain($currentNode->var, 'createQueryBuilder');
		if ($methodCall instanceof MethodCall) {
			$aliasName = UnwrapValue::string($methodCall->getArgs()[0]->value, $scope);
			if ($aliasName === null) {
				throw new ErrorMessage('Variable arguments for aliases are not supported.');
			}

			$left = $scope->getType($methodCall->var);
			if ($left instanceof ThisType) {
				$entityType = $this->entityClassFinder->getEntityClassName($left->getStaticObjectType());
				if ($entityType instanceof ObjectType) {
					$aliasMap->addAlias($aliasName, $entityType->getClassName());
				} else {
					throw new ErrorMessage('Could not determine repository type from: ' . $left->getStaticObjectType()->describe(VerbosityLevel::typeOnly()));
				}
			}
			return $aliasMap;
		}

		throw new ErrorMessage('Could not find source entity from "from" or "createQueryBuilder" methods');
	}
}
