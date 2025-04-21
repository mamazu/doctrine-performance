<?php

declare(strict_types=1);

namespace Test\Mamazu\DoctrinePerformance\Controller;

use Mamazu\DoctrinePerformance\DoctrineQueryBuilderPerformanceRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

class ControllerTest extends RuleTestCase
{
	public function getRule(): Rule
	{
		return new DoctrineQueryBuilderPerformanceRule();
	}

	public function testStuff(): void
	{
		$this->analyse(
		    [__DIR__ . '/ExampleController.php'],
		    []
		);
	}
}
