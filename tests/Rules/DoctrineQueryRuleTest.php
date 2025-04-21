<?php

declare(strict_types=1);

namespace Test\Mamazu\DoctrinePerformance\Rules;

use Mamazu\DoctrinePerformance\Rules\DoctrineQueryBuilderRule;
use Mamazu\DoctrinePerformance\Services\MetadataService;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

class DoctrineQueryRuleTest extends RuleTestCase
{
	public function getRule(): Rule
	{
		return new DoctrineQueryBuilderRule(new MetadataService());
	}

	public function testStuff(): void
	{
		return;

		$this->analyse(
			[__DIR__.'/Fixtures/ExampleController.php'],
			[],
		);
	}
}
