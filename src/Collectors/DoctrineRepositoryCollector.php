<?php

declare(strict_types=1);

namespace Mamazu\DoctrinePerformance\Collectors;

use Doctrine\Persistence\ObjectRepository;
use Mamazu\DoctrinePerformance\Errors\ErrorTrait;
use Mamazu\DoctrinePerformance\Helper\GetEntityFromClassName;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\ArrayItem;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Collector;
use PHPStan\Type\ErrorType;
use PHPStan\Type\IntersectionType;
use PHPStan\Type\MixedType;
use PHPStan\Type\NullType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\ObjectWithoutClassType;
use PHPStan\Type\ThisType;
use PHPStan\Type\Type;
use PHPStan\Type\UnionType;
use PHPStan\Type\VerbosityLevel;

/**
 * @implements Collector<MethodCall>
 * @phpstan-import-type NonIndexedColumData from NonIndexedColumnsRule
 */
class DoctrineRepositoryCollector implements Collector
{
	use ErrorTrait;

	private const RULE_IDENTIFIER = 'doctrine.repository.performance';
	private const RULE_FIND_ALL = 'doctrine.repository.performance.findAll';

	public function __construct(
		private GetEntityFromClassName $entityClassFinder,
		private bool $allowFindAllLikes,
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
		// We don't support dynamic method calls
		if (! $node->name instanceof Node\Identifier) {
			return null;
		}

		// We only care for method calls to the ObjectRepository. Filter out all methods that are not part of that.
		// eg. if UserRepository implements something like findByUsername, then it won't call the ObjectRepository
		$type = new ObjectType(ObjectRepository::class);
		$methodName = (string) $node->name;
		if (! $type->hasMethod($methodName)->yes()) {
			return null;
		}

		// Find always uses the identifier which is indexed
		if ($methodName === 'find' || $methodName === 'getClassName') {
			return null;
		}

		$repositoryType = $scope->getType($node->var);
		// Unwrap this to it's type
		if ($repositoryType instanceof ThisType) {
			$repositoryType = $repositoryType->getStaticObjectType();
		}

		$repositoryType = $this->typeIsRepository($repositoryType);
		if ($repositoryType === null) {
			return null;
		}

		// Checking for Repository vs Repository<Entity>
		$entityType = $this->entityClassFinder->getEntityClassName($repositoryType);
		if ($entityType === null) {
			return [self::genericError(
				'Could not determine entity type on: ' . $repositoryType->describe(VerbosityLevel::typeOnly()),
				 self::RULE_IDENTIFIER . '.unknownRepo',
				$node->getLine(),
				'Use something like /** @var ObjectRepository<Entity> */ to denote the entity of the repository',
			)];
		}

		$entityClass = $entityType->getClassName();
		if ($methodName === 'findAll') {
			if (!$this->allowFindAllLikes) {
				return [self::genericError(
					'findAll is not allowed for performance reason',
					self::RULE_FIND_ALL,
					$node->getLine(),
					'You could use a query builder and and iterator if you really need all entries.'
				)];
			}
			return [];
		} else if (in_array($methodName, ['findBy', 'findOneBy'])){
			/** @var Array_ $filters */
			$filters = $node->args[0]->value;

			if (($filters->items ?? []) === []) {
				return [self::genericError(
					$methodName.' with no filters is not allowed',
					self::RULE_FIND_ALL,
					$filters->getLine(),
					'You could use a query builder and and iterator if you really need all entries.'
				)];
			}

			$usedColumns = $this->getUsedColumns($node->args);
			return [self::nonIndexedColumnError($entityClass, array_keys($usedColumns), $node->getLine())];
		} else {
			// It's a magic doctrine method
			$field = str_replace('findBy', '', str_replace('findOneBy', '', $methodName));
			$field[0] = strtolower($field[0]);

			return [self::nonIndexedColumnError($entityClass, [$field], $node->getLine())];
		}
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
			if (! ($value instanceof Array_)) {
				continue;
			}

			foreach ($value->items as $item) {
				$columns[$item->key->value] = $item;
			}
		}

		return $columns;
	}

	private function typeIsRepository(Type $type): ?Type
	{
		if ($type instanceof NullType || $type instanceof ObjectWithoutClassType || $type instanceof ErrorType || $type instanceof MixedType) {
			return null;
		}

		if ($type instanceof UnionType || $type instanceof IntersectionType) {
			foreach ($type->getTypes() as $type) {
				if ($this->typeIsRepository($type) !== null) {
					return $type;
				}
			}
			return null;
		}

		if ($type->isInstanceOf('Doctrine\Persistence\ObjectRepository')->yes()) {
			return $type;
		}
		return null;
	}
}
