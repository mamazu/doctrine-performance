<?php

declare(strict_types=1);

namespace Mamazu\DoctrinePerformance\Rules;

use Doctrine\Persistence\ObjectRepository;
use Mamazu\DoctrinePerformance\Services\MetadataService;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\ArrayItem;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ErrorType;
use PHPStan\Type\Generic\GenericObjectType;
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
 * @implements Rule<MethodCall>
 */
class DoctrineRepositoryRule implements Rule
{
	private const RULE_IDENTIFIER = 'doctrine.repository.performance';

	public function __construct(
		private MetadataService $metadataService,
		private ReflectionProvider $reflectionProvider,
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
		// We don't support dynamic method calls
		if (! $node->name instanceof Node\Identifier) {
			return [];
		}

		// We only care for method calls to the ObjectRepository. Filter out all methods that are not part of that.
		// eg. if UserRepository implements something like findByUsername, then it won't call the ObjectRepository
		$type = new ObjectType(ObjectRepository::class);
		$methodName = (string) $node->name;
		if (! $type->hasMethod($methodName)->yes()) {
			return [];
		}

		// Find always uses the identifier which is indexed
		if ($methodName === 'find' || $methodName === 'getClassName') {
			return [];
		}

		$repositoryType = $scope->getType($node->var);
		// Unwrap this to it's type
		if ($repositoryType instanceof ThisType) {
			$repositoryType = $repositoryType->getStaticObjectType();
		}

		$repositoryType = $this->typeIsRepository($repositoryType);
		if ($repositoryType === null) {
			return [];
		}

		// Checking for Repository vs Repository<Entity>
		$entityType = $this->getEntityClassName($repositoryType);
		if ($entityType === null) {
			return [
				RuleErrorBuilder::message(
					'Found ' . $repositoryType->describe(VerbosityLevel::typeOnly()) . ' but could not determine type of its entity'
				)
					->identifier(self::RULE_IDENTIFIER . '.unknownRepo')
					->tip('Use something like /** @var ObjectRepository<Entity> */ to denote the entity of the repository')
					->line($node->getLine())
					->build(),
			];
		}

		$entityClass = $entityType->getClassName();
		if ($this->metadataService->shouldEntityBeSkipped($entityClass)) {
			return [];
		}

		$errors = [];
		if (in_array($methodName, ['findBy', 'findOneBy', 'findAll'])){
			$usedColumns = $this->getUsedColumns($node->args);
			$notIndexedColumns = $this->metadataService->nonIndexedColums($entityClass, array_keys($usedColumns));

			foreach ($notIndexedColumns as $notIndexedColumn){
				$token = $usedColumns[$notIndexedColumn];

				$errors[] = RuleErrorBuilder::message(sprintf(
					'Found column "%s" of entity "%s" which is not indexed.',
					$notIndexedColumn,
					$entityClass,
				))
					->identifier(self::RULE_IDENTIFIER)
					->line($token->getLine())
					->build()
				;
			}
		} else {
			// It's a magic doctrine method
			$field = str_replace('findBy', '', str_replace('findOneBy', '', $methodName));
			$field[0] = strtolower($field[0]);

			$notIndexedColumns = $this->metadataService->nonIndexedColums($entityClass, [$field]);
			foreach ($notIndexedColumns as $column) {
				$errors[] = RuleErrorBuilder::message(sprintf(
					'Found column "%s" of entity "%s" which is not indexed.',
					$field,
					$entityClass,
				))
					->identifier(self::RULE_IDENTIFIER)
					->line($node->getLine())
					->build()
				;
			}
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

	private function getEntityClassName($repositoryType): ?ObjectType
	{
		if ($repositoryType instanceof GenericObjectType) {
			$entityType = $repositoryType->getTypes()[0];
			if (! $entityType instanceof ObjectType) {
				return null;
			}

			return $entityType;
		}

		dump($repositoryType->describe(VerbosityLevel::typeOnly()));

		$type = $this->reflectionProvider
			->getClass($repositoryType->getClassName())
			->getAncestorWithClassName(ObjectRepository::class)
			->getActiveTemplateTypeMap()
			->getType('TEntityClass')
		;
		if (! $type instanceof ObjectType) {
			return null;
		}
		return $type;
	}
}
