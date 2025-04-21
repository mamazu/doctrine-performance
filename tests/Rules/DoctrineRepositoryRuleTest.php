<?php

declare(strict_types=1);

namespace Test\Mamazu\DoctrinePerformance\Rules;

use Mamazu\DoctrinePerformance\Rules\DoctrineRepositoryRule;
use Mamazu\DoctrinePerformance\Services\MetadataService;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

class DoctrineRepositoryRuleTest extends RuleTestCase
{
	public function getRule(): Rule
	{
		return new DoctrineRepositoryRule(new MetadataService());
	}

	public function testStuff(): void
	{
		$this->analyse(
			[__DIR__.'/Fixtures/ExampleRepository.php'],
			[],
		);
	}
}
