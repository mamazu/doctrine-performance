<?php

declare(strict_types=1);

namespace Test\Mamazu\DoctrinePerformance\Collectors\Fixtures\Entities;

use Doctrine\ORM\Mapping as ORM;
use Mamazu\DoctrinePerformance\Attributes\SmallTable;

#[SmallTable]
#[ORM\Entity]
#[ORM\Table(name: 'settings')]
class Settings
{
	#[ORM\Id]
	#[ORM\Column(type: 'integer')]
	#[ORM\GeneratedValue]
	private ?int $id = null;

	#[ORM\Column(type: 'string')]
	public string $key;

	#[ORM\Column(type: 'string')]
	public string $value;

	public function getId(): ?int
	{
		return $this->id;
	}
}
