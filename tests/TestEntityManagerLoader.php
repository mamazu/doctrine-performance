<?php

declare(strict_types=1);

namespace Test\Mamazu\DoctrinePerformance;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMSetup;
use Mamazu\DoctrinePerformance\Services\EntityManagerLoaderInterface;

class TestEntityManagerLoader implements EntityManagerLoaderInterface
{
	public function getEntityManager(): EntityManagerInterface {
		$config = ORMSetup::createAttributeMetadataConfiguration(
			paths: [__DIR__ . '/Collectors/Fixtures/Entities'],
			isDevMode: true,
		);
		$connection = DriverManager::getConnection([
			'driver' => 'pdo_sqlite',
			'path' => __DIR__ . '/Rules/db.sqlite',
		], $config);
		return new EntityManager($connection, $config);
	}
}
