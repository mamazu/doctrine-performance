<?php

declare(strict_types=1);

namespace Test\Mamazu\DoctrinePerformance\Helper;

use Doctrine\ORM\EntityRepository;
use Doctrine\Persistence\ObjectRepository;
use Mamazu\DoctrinePerformance\Helper\GetEntityFromClassName;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Type\Generic\GenericObjectType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\VerbosityLevel;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class GetEntityFromClassNameTest extends TestCase
{
	private GetEntityFromClassName $entityFromClassName;

	private MockObject&ReflectionProvider $reflectionProvider;

	public function setUp(): void
	{
		// Setting up the container because the type printer needs it *shrug*
		(new \PHPStan\DependencyInjection\ContainerFactory(__DIR__))
			->create(sys_get_temp_dir(), [__DIR__ . '/../../phpstan.neon'], [])
		;

		$this->reflectionProvider = $this->createMock(ReflectionProvider::class);

		$this->entityFromClassName = new GetEntityFromClassName($this->reflectionProvider);
	}

	/**
	 * When the user uses a repository use the entity of the repository.
	 */
	public function testGettingFromRepository(): void {
		$repositoryClassReflection = $this->createMock(ClassReflection::class)
			->expect(self::once())
			->method('getTypes')
			->willReturn([new ObjectType('SomeEntity')]);

		$repositoryClassReflection = $this->createMock(ClassReflection::class)
			->expect(self::once())
			->method('getAncestorWithClassName')
			->with(ObjectRepository::class)
			->willReturn($objectRepositoryReflection);

		$repositoryClass = 'SomeRepository';
		$this->reflectionProvider->expects(self::once())->method('getClass')
			->with($repositoryClass)
			->willReturn($repositoryClassReflection);

		$this->assertSame(
			'SomeEntity',
			(string) $this->entityFromClassName->getEntityClassName($type)?->describe(VerbosityLevel::typeOnly()),
		);
	}

	public function testGettingFromGeneric(): void
	{
		$this->reflectionProvider->expects(self::never())->method('getClass');

		// This is the same as EntityRepository<SomeEntity>
		$type = new GenericObjectType(EntityRepository::class, [new ObjectType('SomeEntity')]);

		$this->assertSame(EntityRepository::class . '<SomeEntity>', (string) $type->describe(VerbosityLevel::value()));

		$entityType = $this->entityFromClassName->getEntityClassName($type);
		//$this->assertSame(
			//'SomeEntity',
			//(string) ?->describe(VerbosityLevel::typeOnly()),
		//);
	}
}
