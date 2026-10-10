<?php
declare(strict_types=1);

namespace AlumniNexus\Domain\Enums;

enum UserRole: string {
    case ALUMNI = 'alumni';
    case STUDENT = 'student';
    case FACULTY = 'faculty';
    case ADMIN = 'admin';

    public function label(): string {
        return match ($this) {
            self::ALUMNI => 'Graduate / Alumni',
            self::STUDENT => 'Current Undergraduate',
            self::FACULTY => 'Academic Staff',
            self::ADMIN => 'System Administrator',
        };
    }
}
