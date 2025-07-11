<?php

namespace Test\Mamazu\DoctrinePerformance\Collectors;

use Mamazu\DoctrinePerformance\Collectors\DoctrineRepositoryCollector;
use Mamazu\DoctrinePerformance\Helper\GetEntityFromClassName;
use Mamazu\DoctrinePerformance\Rules\NonIndexedColumnsRule;
use Mamazu\DoctrinePerformance\Services\MetadataService;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use Test\Mamazu\DoctrinePerformance\TestEntityManagerLoader;

/**
 * @extends RuleTestCase<NonIndexedColumnsRule>
 */
class DoctrineRepositoryCollectorTest extends RuleTestCase
{
	protected function getRule(): Rule
	{
		$entityManagerLoader = new TestEntityManagerLoader();

		return new NonIndexedColumnsRule(new MetadataService($entityManagerLoader));
	}

	protected function getCollectors(): array
	{
		return [new DoctrineRepositoryCollector(new GetEntityFromClassName($this->createReflectionProvider()), false)];
	}

	public function testRule(): void
	{
		$this->analyse([__DIR__ . '/Fixtures/UsingRepositoryMethods.php'], [
			[
				'Found column "author" of entity "Test\Mamazu\DoctrinePerformance\Collectors\Fixtures\Entities\Books" which is not indexed.',
				24,
			],
			[
				'findBy with no filters is not allowed',
				48,
				'You could use a query builder and and iterator if you really need all entries.', //tip
			],
			[
				'findAll is not allowed for performance reason',
				53,
				'You could use a query builder and and iterator if you really need all entries.', //tip
			],
		]);
	}
}
