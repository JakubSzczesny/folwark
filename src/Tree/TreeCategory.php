<?php

declare(strict_types=1);

namespace App\Tree;

/**
 * Kategoria w drzewie wspólnym dla wszystkich obiektów jednego rodzaju
 * (informacje o maszynach, linki towarów).
 */
interface TreeCategory
{
    public function getId(): ?int;

    public function getName(): string;

    public function getParent(): ?self;

    public function getPosition(): int;

    public function setPosition(int $position): static;
}
