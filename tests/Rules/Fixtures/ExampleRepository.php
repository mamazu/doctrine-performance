<?php

declare(strict_types=1);

namespace Test\Mamazu\DoctrinePerformance\Rules\Fixtures;

use DateTimeImmutable;
use Doctrine\Persistence\ObjectRepository;
use Test\Mamazu\DoctrinePerformance\Rules\Fixtures\Entities\Books;

class ExampleRepository
{
	/**
	 * @param ObjectRepository<Books> $repository
	 */
	public function __construct(
		private ObjectRepository $repository
	) {
	}

	public function getSettings(): void
	{
		// Only author is not indexed
		$a = $this->repository->findBy([
			'author' => 'Some author',
		]);

		// There is an index called "author_and_genre"
		$b = $this->repository->findBy([
			'author' => 'Some author',
			'genre' => 'on',
		]);

		// There is a unique constraint called "title_and_author"
		$c = $this->repository->findBy([
			'title' => 'Testing',
			'author' => 'Some author',
		]);

		$d = $this->repository->findBy([
			'id' => 'true',
			'publishDate' => new DateTimeImmutable(),
		]);
	}

	//public function queryIt() {
		//$this->repository
			//->createQueryBuilder('book')
			//->where('author = :name')
		//;
	//}
}
