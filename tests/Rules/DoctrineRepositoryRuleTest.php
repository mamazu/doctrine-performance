<?php

declare(strict_types=1);

namespace Test\Mamazu\DoctrinePerformance\Rules;

use Mamazu\DoctrinePerformance\Helper\GetEntityFromClassName;
use Mamazu\DoctrinePerformance\Rules\DoctrineRepositoryRule;
use Mamazu\DoctrinePerformance\Services\MetadataService;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use Test\Mamazu\DoctrinePerformance\TestEntityManagerLoader;

/**
 * @extends RuleTestCase<DoctrineRepositoryRule>
 */
class DoctrineRepositoryRuleTest extends RuleTestCase
{
	public function getRule(): Rule
	{
		$entityManagerLoader = new TestEntityManagerLoader();
		return new DoctrineRepositoryRule(
			new GetEntityFromClassName($this->createReflectionProvider()),
			new MetadataService($entityManagerLoader),
		);
	}

	public function testStuff(): void
	{
		$this->analyse(
			[__DIR__ . '/Fixtures/ExampleRepository.php'],
			[
				[
					'Found column "author" of entity "Test\Mamazu\DoctrinePerformance\Rules\Fixtures\Entities\Books" which is not indexed.',
					25,
				],
			]
		);
	}
}
