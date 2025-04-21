<?php

declare(strict_types=1);

namespace Test\Mamazu\DoctrinePerformance\Entities;

use DateTimeInterface;
use Doctrine\ORM\Mapping\Entity;

#[Entity]
class Books
{
    public string $name;

    public string $author;

    public DateTimeInterface $publishedAt;
}
