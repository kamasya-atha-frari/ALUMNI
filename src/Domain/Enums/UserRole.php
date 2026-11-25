<?php
declare(strict_types=1);

namespace AlumniIstü\Domain\Enums;

enum UserRole: string {
    case ALUMNI = 'alumni';
    case STUDENT = 'student';
    case FACULTY = 'faculty';
    case EMPLOYER = 'employer';
    case MODERATOR = 'moderator';
    case ADMIN = 'admin';

    public function label(): string {
        return match ($this) {
            self::ALUMNI => 'Graduate / Alumni',
            self::STUDENT => 'Current Undergraduate',
            self::FACULTY => 'Academic Staff',
            self::EMPLOYER => 'Corporate Employer / Recruiter',
            self::MODERATOR => 'Network Moderator',
            self::ADMIN => 'System Administrator',
        };
    }

    /**
     * Roles permitted during public self-registration (/api/v1/auth/register).
     * Privileged roles (faculty, moderator, admin) must be assigned by an administrator.
     *
     * @return string[]
     */
    public static function selfRegistrationRoles(): array {
        return [
            self::ALUMNI->value,
            self::STUDENT->value,
            self::EMPLOYER->value,
        ];
    }

    /**
     * Coarse-grained RBAC capability list for introspection and middleware checks.
     *
     * @return string[]
     */
    public function permissions(): array {
        return match ($this) {
            self::STUDENT => [
                'directory:read',
                'profile:manage_own',
                'jobs:read',
                'applications:create',
                'applications:read_own',
                'mentorship:request',
            ],
            self::ALUMNI => [
                'directory:read',
                'profile:manage_own',
                'jobs:read',
                'jobs:create',
                'jobs:manage_own',
                'applications:create',
                'applications:read_own',
                'applications:review_own_jobs',
                'mentorship:request',
                'mentorship:respond',
            ],
            self::EMPLOYER => [
                'directory:read',
                'profile:manage_own',
                'companies:manage_own',
                'jobs:read',
                'jobs:create',
                'jobs:manage_own',
                'applications:review_own_jobs',
            ],
            self::FACULTY => [
                'directory:read',
                'profile:manage_own',
                'jobs:read',
                'analytics:full',
                'departments:verify',
            ],
            self::MODERATOR => [
                'directory:read',
                'profile:manage_own',
                'jobs:read',
                'jobs:moderate',
                'applications:moderate',
                'mentorship:moderate',
                'users:suspend',
                'analytics:full',
            ],
            self::ADMIN => [
                '*',
            ],
        };
    }
}
