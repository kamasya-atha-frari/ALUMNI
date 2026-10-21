<?php
declare(strict_types=1);

namespace AlumniNexus\Database\Factories;

class JobPostingFactory {
    private static array $companies = [
        ['id' => 1, 'name' => 'Trendyol Group', 'industry' => 'E-Commerce & Logistics'],
        ['id' => 2, 'name' => 'Turkcell Tech', 'industry' => 'Telecommunications & Cloud'],
        ['id' => 3, 'name' => 'Getir Global', 'industry' => 'FinTech & Rapid Delivery'],
        ['id' => 4, 'name' => 'Garanti BBVA Technology', 'industry' => 'Banking & Digital Security'],
        ['id' => null, 'name' => 'Akbank TAS', 'industry' => 'FinTech & Banking'],
        ['id' => null, 'name' => 'Aselsan', 'industry' => 'Defense & Embedded Systems'],
        ['id' => null, 'name' => 'Insider', 'industry' => 'SaaS & Marketing Tech'],
        ['id' => null, 'name' => 'Peak Games', 'industry' => 'Gaming & Entertainment'],
        ['id' => null, 'name' => 'Hepsiburada', 'industry' => 'E-Commerce & Marketplace'],
        ['id' => null, 'name' => 'BtcTurk', 'industry' => 'Crypto & Blockchain Infrastructure']
    ];

    private static array $positions = [
        [
            'title' => 'Lead Data Platform Engineer',
            'desc' => 'Architect next-generation big data distributed computation platforms handling millions of streaming events per second.',
            'req' => 'Proven expertise with Apache Spark, Kafka, Python, Go, and Snowflake/BigQuery. Degree in MIS, Computer Science or equivalent.',
            'salary' => '₺110,000 - ₺160,000 / month',
            'exp' => 'Lead',
            'type' => 'full-time'
        ],
        [
            'title' => 'Senior Backend Engineer (PHP / Go)',
            'desc' => 'Develop high-throughput microservices, public APIs, and transaction-safe order processing pipelines.',
            'req' => 'Strong proficiency in modern PHP 8.2+, Go, Docker, PostgreSQL/MySQL, and Redis caching architectures.',
            'salary' => '₺85,000 - ₺125,000 / month',
            'exp' => 'Senior',
            'type' => 'full-time'
        ],
        [
            'title' => 'Technical Product Manager',
            'desc' => 'Drive product strategy and execution for core recommendation algorithms and data analytics workflows.',
            'req' => 'Background in Management Information Systems (YBS). Demonstrated mastery of SQL, Jira, PRDs, and user research.',
            'salary' => '₺75,000 - ₺115,000 / month',
            'exp' => 'Mid-Level',
            'type' => 'full-time'
        ],
        [
            'title' => 'Cloud & DevOps SRE Engineer',
            'desc' => 'Automate Kubernetes cluster orchestration, multi-cloud Terraform templates, and zero-downtime CI/CD delivery pipelines.',
            'req' => 'Experience with AWS/GCP, Docker, Terraform, Prometheus, and Grafana. Excellent incident management skills.',
            'salary' => '₺90,000 - ₺135,000 / month',
            'exp' => 'Senior',
            'type' => 'remote'
        ],
        [
            'title' => 'Cybersecurity & Threat Analyst',
            'desc' => 'Monitor enterprise SOC alerts, perform vulnerability assessments, and implement zero-trust access controls.',
            'req' => 'Knowledge of SIEM platforms (Splunk/QRadar), ISO 27001 standards, and network security protocols.',
            'salary' => '₺70,000 - ₺100,000 / month',
            'exp' => 'Mid-Level',
            'type' => 'full-time'
        ],
        [
            'title' => 'Machine Learning & AI Specialist',
            'desc' => 'Build and deploy predictive churn models, automated NLP embeddings, and customer lifetime value algorithms.',
            'req' => 'Strong mathematical background, PyTorch/TensorFlow, MLflow, and automated inference pipeline experience.',
            'salary' => '₺95,000 - ₺145,000 / month',
            'exp' => 'Senior',
            'type' => 'hybrid'
        ],
        [
            'title' => 'Undergraduate Software Intern',
            'desc' => 'Exciting 6-month hands-on internship working alongside senior engineering squads on production codebases.',
            'req' => 'Currently enrolled in 3rd or 4th year Istanbul University MIS or Engineering faculties. Good OOP fundamentals.',
            'salary' => '₺25,000 - ₺35,000 / month',
            'exp' => 'Entry',
            'type' => 'internship'
        ],
        [
            'title' => 'Data Analytics & BI Consultant',
            'desc' => 'Transform enterprise operational data into executive dashboards, cohort retention funnels, and automated KPI reports.',
            'req' => 'Advanced SQL, PowerBI / Tableau, dbt, and strong storytelling with quantitative metrics.',
            'salary' => '₺65,000 - ₺95,000 / month',
            'exp' => 'Mid-Level',
            'type' => 'full-time'
        ]
    ];

    private static array $locations = [
        'Istanbul, Turkey (Hybrid)',
        'Istanbul, Turkey (Maslak Office)',
        'Istanbul, Turkey (Kartal Tech Hub)',
        'Ankara, Turkey (ODTÜ Teknokent)',
        'Izmir, Turkey',
        'Remote (Worldwide)',
        'Amsterdam, Netherlands'
    ];

    public static function definition(array $overrides = []): array {
        $pos = self::$positions[array_rand(self::$positions)];
        $company = self::$companies[array_rand(self::$companies)];
        $location = self::$locations[array_rand(self::$locations)];
        $jobTypes = ['full-time', 'remote', 'internship', 'part-time', 'contract'];
        $jobType = $pos['type'] === 'hybrid' ? 'full-time' : ($pos['type'] ?? $jobTypes[array_rand($jobTypes)]);

        $default = [
            'title'            => $pos['title'],
            'company_id'       => $company['id'],
            'company_name'     => $company['name'],
            'user_id'          => rand(1, 4),
            'description'      => $pos['desc'],
            'requirements'     => $pos['req'],
            'location'         => $location,
            'job_type'         => $jobType,
            'salary_range'     => $pos['salary'],
            'experience_level' => $pos['exp'],
            'status'           => 'active'
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
