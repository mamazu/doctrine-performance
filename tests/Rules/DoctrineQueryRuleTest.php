<?php

declare(strict_types=1);

namespace Test\Mamazu\DoctrinePerformance\Rules;

use Mamazu\DoctrinePerformance\Rules\DoctrineQueryBuilderRule;
use Mamazu\DoctrinePerformance\Services\MetadataService;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;

class DoctrineQueryRuleTest extends RuleTestCase
{
	public function getRule(): Rule
	{
		$config = ORMSetup::createAttributeMetadataConfiguration(
			paths: [__DIR__ . '/Fixtures/Entities'],
			isDevMode: true,
		);
		$connection = DriverManager::getConnection([
			'driver' => 'pdo_sqlite',
			'path' => __DIR__ . '/db.sqlite',
		], $config);

		$entityManager = new EntityManager($connection, $config);
		return new DoctrineQueryBuilderRule(new MetadataService($entityManager));
	}

	public function testStuff(): void
	{
		$this->analyse(
			[__DIR__.'/Fixtures/ExampleController.php'],
			[],
		);
	}
}
