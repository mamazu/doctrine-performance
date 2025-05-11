<?php

declare(strict_types=1);

namespace Test\Mamazu\DoctrinePerformance\Rules;

use Mamazu\DoctrinePerformance\Helper\GetEntityFromClassName;
use Mamazu\DoctrinePerformance\Rules\DoctrineQueryBuilderRule;
use Mamazu\DoctrinePerformance\Services\MetadataService;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use Test\Mamazu\DoctrinePerformance\TestEntityManagerLoader;

/**
 * @extends RuleTestCase<DoctrineQueryBuilderRule>
 */
class DoctrineQueryRuleTest extends RuleTestCase
{
	public function getRule(): Rule
	{
		$entityManagerLoader = new TestEntityManagerLoader();
		return new DoctrineQueryBuilderRule(
			new GetEntityFromClassName($this->createReflectionProvider()),
			new MetadataService($entityManagerLoader),
		);
	}

	public function testStuff(): void
	{
		$this->analyse([__DIR__ . '/Fixtures/ExampleController.php'], []);
	}

	public function testGettingEntityType(): void
	{
		$this->analyse([__DIR__ . '/Fixtures/ExtendingRepository.php'], []);
	}
}
