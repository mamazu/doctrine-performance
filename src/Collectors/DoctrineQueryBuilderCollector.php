<?php

declare(strict_types=1);

namespace Mamazu\DoctrinePerformance\Collectors;

use Generator;
use Mamazu\DoctrinePerformance\Errors\ErrorMessage;
use Mamazu\DoctrinePerformance\Helper\AliasMap;
use Mamazu\DoctrinePerformance\Helper\GetEntityFromClassName;
use Mamazu\DoctrinePerformance\Helper\UnwrapValue;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Type\UnionType;
use PHPStan\Type\MixedType;
use PHPStan\Type\ThisType;
use PHPStan\Type\VerbosityLevel;
use PHPStan\Type\ObjectType;
use PHPStan\Collectors\Collector;

/**
 * @implements Rule<MethodCall>
 * @phpstan-import-type NonIndexedColumData from NonIndexedColumnsRule
 */
class DoctrineQueryBuilderCollector implements Collector
{
	private const RULE_IDENTIFIER = 'doctrine.queryBuilder.performance';

	// Issued when the argument of the where expression is not a string constant
	private const RULE_IDENTIFIER_NOT_SUPPORTED = 'doctrine.queryBuilder.performance.nonStringWhere';

	// Issued when there is no way to identify the entity name from types
	private const RULE_IDENTIFIER_NO_ENTITY_FOUND = 'doctrine.queryBuilder.performance.noEntityFound';

	public function __construct(
		private GetEntityFromClassName $entityClassFinder,
	) {}

	public function getNodeType(): string
	{
		return MethodCall::class;
	}

	/**
	 * @param MethodCall $node
	 *
	 * @return NonIndexedColumData
	 */
	public function processNode(Node $node, Scope $scope): ?array
	{
		// Ignore all non where, orWhere, andWhere methods
		if (! $node->name instanceof Node\Identifier || (stripos((string) $node->name, 'where')) === false) {
			return null;
		}

		// Get the type of the object the method is called on
		$calledOnType = $scope->getType($node->var);

		if ($calledOnType instanceof ThisType || $calledOnType instanceof MixedType || $calledOnType instanceof UnionType) {
			return null;
		}

		// Check if its a queryBuilder (Doctrine\ORM\QueryBuilder or Doctrine\DBAL\Query\QueryBuilder)
		if ($calledOnType->isInstanceOf('Doctrine\ORM\QueryBuilder')->no() &&
			$calledOnType->isInstanceOf('Doctrine\DBAL\Query\QueryBuilder')
				->no()) {
			return null;
		}

		try {
			$aliasMap = $this->getAliasMap($node, $scope);
		} catch (ErrorMessage $error) {
			var_dump($error->getMessage());
			return null;
				//RuleErrorBuilder::message($error->getMessage())
					//->identifier(self::RULE_IDENTIFIER_NO_ENTITY_FOUND)
					//->build(),
		}

		// Extract Expression from method call
		$argument = $node->getArgs()[0];
		$queryString = $argument->value;
		if (!$queryString instanceof String_) {
			return null;
				//RuleErrorBuilder::message('Non constant strings in where method is not supported.')
					//->identifier(self::RULE_IDENTIFIER_NOT_SUPPORTED)
					//->build(),
		}

		$errors = [];
		foreach ($this->parseArgumentAndRetunUnindexedColumns($aliasMap, $queryString->value) as $className => $fields) {
			$errors[] = [$className, $fields, $argument->getLine()];
		}
		return $errors;
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
	 * @return Generator<class-string, array<string>>
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
			yield $className => $fields;
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
			// First argument is the alias name
			$aliasName = UnwrapValue::string($methodCall->getArgs()[0]->value, $scope);
			if ($aliasName === null) {
				throw new ErrorMessage('Variable arguments for aliases are not supported.');
			}

			// Get the class name of the entity
			$left = $scope->getType($methodCall->var);
			if ($left instanceof ThisType) {
				$entityType = $this->entityClassFinder->getEntityClassName($left->getStaticObjectType());
				if ($entityType instanceof ObjectType) {
					$aliasMap->addAlias($aliasName, $entityType->getClassName());
				} else {
					throw new ErrorMessage('Could not determine repository type from: ' . $left->getStaticObjectType()->describe(VerbosityLevel::typeOnly()));
				}
			} else {
				throw new ErrorMessage('Unable to determine type of dynamic repository');
			}
			return $aliasMap;
		}

		throw new ErrorMessage('Could not find source entity from "from" or "createQueryBuilder" methods');
	}
}
