<?php

declare(strict_types=1);

namespace Test\Mamazu\DoctrinePerformance\Rules\Fixtures\Entities;

use Mamazu\DoctrinePerformance\Attributes\SmallTable;

#[SmallTable]
class Settings
{
	public string $name1;
	public string $name2;
	public string $name3;
	public string $name4;
	public string $name5;
	public string $name6;
	public string $name7;
	public string $name8;
}
