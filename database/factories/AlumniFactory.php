<?php
declare(strict_types=1);

namespace AlumniNexus\Database\Factories;

class AlumniFactory {
    private static array $firstNames = [
        'Ahmet', 'Mehmet', 'Can', 'Zeynep', 'Elif', 'Burak', 'Selin', 'Emre',
        'Aylin', 'Murat', 'Deniz', 'Gizem', 'Onur', 'Kaan', 'Ece', 'Büşra',
        'Yusuf', 'Cem', 'Tolga', 'Gamze', 'Berk', 'Hazal', 'Oğuz', 'Damla'
    ];

    private static array $lastNames = [
        'Yılmaz', 'Kaya', 'Demir', 'Çelik', 'Şahin', 'Yıldız', 'Yıldırım',
        'Öztürk', 'Aydın', 'Özdemir', 'Arslan', 'Doğan', 'Kılıç', 'Aslan',
        'Çetin', 'Kara', 'Koç', 'Kurt', 'Özkan', 'Şimşek', 'Polat', 'Korkmaz'
    ];

    private static array $companies = [
        ['name' => 'Turkcell Tech', 'industry' => 'Telecommunications & Cloud'],
        ['name' => 'Trendyol Group', 'industry' => 'E-Commerce & Logistics'],
        ['name' => 'Getir Global', 'industry' => 'FinTech & Rapid Delivery'],
        ['name' => 'Garanti BBVA Technology', 'industry' => 'Banking & Digital Security'],
        ['name' => 'Akbank TAS', 'industry' => 'FinTech & Banking'],
        ['name' => 'Aselsan', 'industry' => 'Defense & Embedded Systems'],
        ['name' => 'Insider', 'industry' => 'SaaS & Marketing Tech'],
        ['name' => 'Peak Games', 'industry' => 'Gaming & Entertainment'],
        ['name' => 'Hepsiburada', 'industry' => 'E-Commerce & Marketplace'],
        ['name' => 'BtcTurk', 'industry' => 'Crypto & Blockchain Infrastructure']
    ];

    private static array $roles = [
        'Lead Data Engineer',
        'Senior Product Manager',
        'DevOps & Site Reliability Engineer',
        'Cybersecurity Analyst',
        'Full Stack Software Engineer',
        'Machine Learning Engineer',
        'Cloud Solutions Architect',
        'Scrum Master & Agile Coach',
        'Business Systems Analyst',
        'Mobile Application Developer'
    ];

    private static array $locations = [
        'Istanbul, Turkey', 'Ankara, Turkey', 'Izmir, Turkey',
        'Amsterdam, Netherlands', 'Berlin, Germany', 'London, UK'
    ];

    private static array $availableSkills = [
        'Python', 'SQL', 'Apache Spark', 'Databricks', 'Docker', 'Kubernetes',
        'AWS', 'Terraform', 'CI/CD', 'Product Strategy', 'Agile/Scrum',
        'Data Analytics', 'A/B Testing', 'SIEM', 'Network Security', 'ISO 27001',
        'Penetration Testing', 'React', 'TypeScript', 'Node.js', 'Go', 'Java', 'PHP'
    ];

    public static function definition(array $overrides = []): array {
        $first = self::$firstNames[array_rand(self::$firstNames)];
        $last = self::$lastNames[array_rand(self::$lastNames)];
        $fullName = "{$first} {$last}";

        $slugFirst = strtolower(preg_replace('/[^a-zA-Z]/', '', iconv('UTF-8', 'ASCII//TRANSLIT', $first)));
        $slugLast = strtolower(preg_replace('/[^a-zA-Z]/', '', iconv('UTF-8', 'ASCII//TRANSLIT', $last)));
        $randomNum = rand(100, 999);
        $email = "{$slugFirst}.{$slugLast}{$randomNum}@ogr.iu.edu.tr";

        $studentId = '130' . rand(18, 24) . sprintf('%04d', rand(1, 9999));
        $cohort = rand(2018, 2025);

        $companyInfo = self::$companies[array_rand(self::$companies)];
        $role = self::$roles[array_rand(self::$roles)];
        $location = self::$locations[array_rand(self::$locations)];

        // Pick 3 to 5 random skills
        $skillKeys = (array)array_rand(self::$availableSkills, rand(3, 5));
        $skills = array_values(array_intersect_key(self::$availableSkills, array_flip($skillKeys)));

        $isMentor = (bool)(rand(0, 10) > 4);
        $mentorCapacity = $isMentor ? rand(1, 4) : 0;

        $default = [
            'student_id' => $studentId,
            'full_name' => $fullName,
            'email' => $email,
            'cohort' => $cohort,
            'department_id' => 1,
            'company' => $companyInfo['name'],
            'role' => $role,
            'industry' => $companyInfo['industry'],
            'location' => $location,
            'skills' => $skills,
            'is_mentor' => $isMentor,
            'mentor_capacity' => $mentorCapacity
        ];

        return array_merge($default, $overrides);
    }

    public static function count(int $count, array $overrides = []): array {
        $items = [];
        for ($i = 0; $i < $count; $i++) {
            $items[] = self::definition($overrides);
        }
        return $items;
    }
}
