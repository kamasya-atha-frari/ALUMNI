<?php
declare(strict_types=1);

namespace AlumniNexus\Database\Factories;

class ApplicationFactory {
    private static array $firstNames = [
        'Zeynep', 'Kaan', 'Elif', 'Oğuz', 'Damla', 'Emre', 'Selin', 'Burak',
        'Cem', 'Hazal', 'Berk', 'Gizem', 'Murat', 'Deniz', 'Tolga', 'Ece'
    ];

    private static array $lastNames = [
        'Aydın', 'Özdemir', 'Kılıç', 'Polat', 'Yıldırım', 'Kara', 'Kurt', 'Şimşek',
        'Çetin', 'Doğan', 'Aslan', 'Koç', 'Korkmaz', 'Özkan', 'Şahin', 'Yıldız'
    ];

    private static array $letters = [
        'As an Istanbul University MIS graduate, I have closely tracked your technological achievements. My background in distributed data pipelines and database architecture makes me an ideal contributor to your squad.',
        'I am excited to submit my candidacy. Having completed extensive coursework in cloud infrastructures, containerization, and modern APIs, I am eager to apply my analytical rigor to your team.',
        'With hands-on experience in full-stack engineering and agile delivery, I am confident in my ability to drive high-impact features and exceed performance targets.',
        'Having served as student representative in the Department of Management Information Systems, I bring both technical acumen in Python/SQL and exceptional cross-functional communication abilities.'
    ];

    private static array $statuses = [
        'submitted', 'under_review', 'interview', 'accepted', 'rejected'
    ];

    public static function definition(int $jobPostingId = 1, array $overrides = []): array {
        $first = self::$firstNames[array_rand(self::$firstNames)];
        $last = self::$lastNames[array_rand(self::$lastNames)];
        $fullName = "{$first} {$last}";

        $slugFirst = strtolower(preg_replace('/[^a-zA-Z]/', '', iconv('UTF-8', 'ASCII//TRANSLIT', $first)));
        $slugLast = strtolower(preg_replace('/[^a-zA-Z]/', '', iconv('UTF-8', 'ASCII//TRANSLIT', $last)));
        $email = "{$slugFirst}.{$slugLast}" . rand(10, 99) . '@ogr.iu.edu.tr';

        $letter = self::$letters[array_rand(self::$letters)];
        $status = self::$statuses[array_rand(self::$statuses)];

        $default = [
            'job_posting_id'  => $jobPostingId,
            'user_id'         => rand(1, 10) > 4 ? rand(1, 4) : null,
            'applicant_name'  => $fullName,
            'applicant_email' => $email,
            'resume_url'      => 'https://drive.google.com/file/d/' . md5($email) . '-resume.pdf',
            'cover_letter'    => $letter,
            'status'          => $status
        ];

        return array_merge($default, $overrides);
    }

    public static function count(int $count, int $jobPostingId = 1, array $overrides = []): array {
        $items = [];
        for ($i = 0; $i < $count; $i++) {
            $items[] = self::definition($jobPostingId, $overrides);
        }
        return $items;
    }
}
