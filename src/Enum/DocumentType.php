<?php

declare(strict_types=1);

namespace App\Enum;

enum DocumentType: string
{
    case Pz = 'PZ';
    case Pw = 'PW';
    case Wz = 'WZ';
    case Rw = 'RW';
    case Mm = 'MM';
    case Inw = 'INW';

    public function label(): string
    {
        return match ($this) {
            self::Pz => 'PZ – przyjęcie zewnętrzne',
            self::Pw => 'PW – przyjęcie wewnętrzne',
            self::Wz => 'WZ – wydanie zewnętrzne',
            self::Rw => 'RW – rozchód wewnętrzny',
            self::Mm => 'MM – przesunięcie',
            self::Inw => 'INW – inwentaryzacja',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::Pz => 'przyjęcie od dostawcy',
            self::Pw => 'przyjęcie własne (np. zbiory)',
            self::Wz => 'wydanie odbiorcy',
            self::Rw => 'zużycie w gospodarstwie',
            self::Mm => 'przesunięcie między magazynami',
            self::Inw => 'stan faktyczny ze spisu',
        };
    }

    public function needsSource(): bool
    {
        return \in_array($this, [self::Wz, self::Rw, self::Mm], true);
    }

    public function needsTarget(): bool
    {
        return \in_array($this, [self::Pz, self::Pw, self::Mm, self::Inw], true);
    }

    public function hasContractor(): bool
    {
        return \in_array($this, [self::Pz, self::Wz], true);
    }

    /**
     * Przyjęcia mają cenę z dokumentu; wydania wyceniamy średnią ważoną.
     */
    public function isPriced(): bool
    {
        return \in_array($this, [self::Pz, self::Pw], true);
    }
}
