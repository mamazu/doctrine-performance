<?php

use Mamazu\DoctrinePerformance\Collectors\DoctrineQueryBuilderCollector;
use Mamazu\DoctrinePerformance\Helper\GetEntityFromClassName;
use Mamazu\DoctrinePerformance\Rules\NonIndexedColumnsRule;
use Mamazu\DoctrinePerformance\Services\MetadataService;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use Test\Mamazu\DoctrinePerformance\TestEntityManagerLoader;

/**
 * @extends RuleTestCase<NonIndexedColumnsRule>
 */
class DoctrineQueryBuilderCollectorTest extends RuleTestCase
{
	protected function getRule(): Rule
	{
		$entityManagerLoader = new TestEntityManagerLoader();

		return new NonIndexedColumnsRule(new MetadataService($entityManagerLoader));
	}

	protected function getCollectors(): array
	{
		return [new DoctrineQueryBuilderCollector(
			new GetEntityFromClassName($this->createReflectionProvider()),
			reportTooDynamic: false,
		)];
	}

	public function testRule(): void
	{
		$this->analyse([__DIR__ . '/Fixtures/UsingQueryBuilder.php'], [
			[
				'Found column "author" of entity "Test\Mamazu\DoctrinePerformance\Collectors\Fixtures\Entities\Books" which is not indexed.',
				36,
			],
		]);
	}

	public function testRuleWithMetadata(): void
	{
		$this->analyse([__DIR__ . '/Fixtures/ExtendingRepository.php'], [
			[
				'Found column "nonIndexed" of entity "Test\Mamazu\DoctrinePerformance\Collectors\Fixtures\Entities\Books" which is not indexed.',
				18,
			],
		]);
	}
}
