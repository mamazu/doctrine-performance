<?php

declare(strict_types=1);

namespace Test\Mamazu\DoctrinePerformance\Rules\Fixtures\Entities;

use DatetimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'books')]
#[ORM\Index(name: 'publishDate', columns: ['publishDate'])]
#[ORM\Index(name: 'author_and_genre', columns: ['author', 'genre'])]
#[ORM\UniqueConstraint(name: 'title_and_author', columns: ['title', 'author'])]
class Books
{
	#[ORM\Id]
	#[ORM\Column(type: 'integer')]
	#[ORM\GeneratedValue]
	private int|null $id = null;

	#[ORM\Column(type: 'string', unique: true)]
	public string $title;

	#[ORM\Column(type: 'string')]
	public string $author;

	#[ORM\Column(type: 'string')]
	public string $genre;

	#[ORM\Column(type: 'datetime_immutable')]
	public DatetimeImmutable $publishDate;
}
