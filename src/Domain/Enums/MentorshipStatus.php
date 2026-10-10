<?php
declare(strict_types=1);

namespace AlumniNexus\Domain\Enums;

enum MentorshipStatus: string {
    case AVAILABLE = 'available';
    case AT_CAPACITY = 'at_capacity';
    case INACTIVE = 'inactive';

    public function badgeColor(): string {
        return match ($this) {
            self::AVAILABLE => 'emerald',
            self::AT_CAPACITY => 'amber',
            self::INACTIVE => 'slate',
        };
    }
}
