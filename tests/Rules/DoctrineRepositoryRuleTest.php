<?php

declare(strict_types=1);

namespace Test\Mamazu\DoctrinePerformance\Rules;

use Doctrine\ORM\EntityManager;
use Mamazu\DoctrinePerformance\Rules\DoctrineRepositoryRule;
use Mamazu\DoctrinePerformance\Services\MetadataService;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\ORMSetup;

class DoctrineRepositoryRuleTest extends RuleTestCase
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

		$em = new EntityManager($connection, $config);
		return new DoctrineRepositoryRule(new MetadataService($em));
	}

	public function testStuff(): void
	{
		$this->analyse(
			[__DIR__.'/Fixtures/ExampleRepository.php'],
			[
				[
					'Found column "author" of entity "Test\Mamazu\DoctrinePerformance\Rules\Fixtures\Entities\Books" which is not indexed.',
					26,
				],
			]
		);
	}
}
