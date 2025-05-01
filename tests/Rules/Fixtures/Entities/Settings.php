<?php

declare(strict_types=1);

namespace Test\Mamazu\DoctrinePerformance\Rules\Fixtures\Entities;

use Mamazu\DoctrinePerformance\Attributes\SmallTable;
use Doctrine\ORM\Mapping as ORM;

#[SmallTable]
#[ORM\Entity]
#[ORM\Table(name: 'settings')]
class Settings
{
	#[ORM\Id]
	#[ORM\Column(type: 'integer')]
	#[ORM\GeneratedValue]
	private int|null $id = null;

	#[ORM\Column(type: 'string')]
	public string $key;

	#[ORM\Column(type: 'string')]
	public string $value;
}
