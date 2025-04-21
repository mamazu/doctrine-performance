<?php

declare(strict_types=1);

namespace Test\Mamazu\DoctrinePerformance\Entities;

use Doctrine\ORM\Mapping\Entity;
use Mamazu\DoctrinePerformance\Attributes\SmallTable;

#[Entity]
#[SmallTable]
class Settings
{
    public string $setting;

    public string $value;
}
