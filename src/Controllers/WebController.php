<?php
declare(strict_types=1);

namespace AlumniIstü\Controllers;

use AlumniIstü\Core\Response;
use AlumniIstü\Services\AlumniService;

class WebController {
    private AlumniService $service;

    public function __construct() {
        $this->service = new AlumniService();
    }

    public function welcome(): void {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        if (isset($_GET['raw']) || isset($_GET['test']) || (str_starts_with($accept, 'text/plain') && !str_contains($accept, 'text/html'))) {
            Response::text("ok");
        }

        $alumniList = $this->service->getAll();
        $totalAlumni = count($alumniList);
        $totalMentors = count(array_filter($alumniList, fn($a) => !empty($a['is_mentor'])));

        Response::html(__DIR__ . '/../../templates/welcome.html', [
            'TOTAL_ALUMNI' => (string)$totalAlumni,
            'TOTAL_MENTORS' => (string)$totalMentors,
        ]);
    }

    public function directory(): void {
        $this->home();
    }

    public function home(): void {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        if (isset($_GET['raw']) || isset($_GET['test']) || (str_starts_with($accept, 'text/plain') && !str_contains($accept, 'text/html'))) {
            Response::text("ok");
        }

        // MVC Flow: Query Model/Service layer for initial Directory telemetry
        $alumniList = $this->service->getAll();
        $totalAlumni = count($alumniList);
        $totalMentors = count(array_filter($alumniList, fn($a) => !empty($a['is_mentor'])));

        Response::html(__DIR__ . '/../../templates/dashboard.html', [
            'TOTAL_ALUMNI' => (string)$totalAlumni,
            'TOTAL_MENTORS' => (string)$totalMentors,
        ]);
    }

    public function about(): void {
        Response::html(__DIR__ . '/../../templates/about.html');
    }

    public function register(): void {
        Response::html(__DIR__ . '/../../templates/register.html');
    }

    public function login(): void {
        Response::html(__DIR__ . '/../../templates/login.html');
    }

    public function admin(): void {
        Response::html(__DIR__ . '/../../templates/admin.html');
    }

    public function myProfile(): void {
        Response::html(__DIR__ . '/../../templates/my-profile.html');
    }

    public function analytics(): void {
        Response::html(__DIR__ . '/../../templates/analytics.html');
    }

    public function swagger(): void {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        if (str_contains($accept, 'application/json') || isset($_GET['json'])) {
            $file = __DIR__ . '/../../docs/openapi.json';
            if (file_exists($file)) {
                header('Content-Type: application/json; charset=utf-8');
                header('Access-Control-Allow-Origin: *');
                echo file_get_contents($file);
                exit;
            }
        }
        Response::html(__DIR__ . '/../../templates/swagger.html');
    }

    public function jobs(): void {
        Response::html(__DIR__ . '/../../templates/jobs.html');
    }

    public function applications(): void {
        Response::html(__DIR__ . '/../../templates/jobs.html');
    }

    public function profile(string $id): void {
        $alumni = $this->service->findById((int)$id);
        if (!$alumni) {
            Response::html(__DIR__ . '/../../templates/404.html', [
                'PATH' => "/alumni/{$id}",
                'REASON' => "Alumni profile #{$id} was not found in the directory."
            ], 404);
            return;
        }
        Response::html(__DIR__ . '/../../templates/profile.html', [
            'ALUMNI_ID' => (string)((int)$id),
            'FULL_NAME' => htmlspecialchars((string)($alumni['full_name'] ?? 'Fellow')),
            'ROLE'      => htmlspecialchars((string)($alumni['role'] ?? 'Technology Professional')),
            'COMPANY'   => htmlspecialchars((string)($alumni['company'] ?? 'Enterprise Partner')),
            'COHORT'    => htmlspecialchars((string)($alumni['cohort'] ?? '2024')),
            'EMAIL'     => htmlspecialchars((string)($alumni['email'] ?? '')),
            'LOCATION'  => htmlspecialchars((string)($alumni['location'] ?? 'Istanbul, Turkey')),
            'INDUSTRY'  => htmlspecialchars((string)($alumni['industry'] ?? 'Technology & MIS')),
        ]);
    }

    public function hello(): void {
        header('Content-Type: text/plain; charset=utf-8');
        echo "Hello, World!";
    }

    public function helloName(string $name): void {
        header('Content-Type: text/plain; charset=utf-8');
        echo "Hello, " . ucfirst($name) . "!";
    }

    public function sum(string $number1, string $number2): void {
        $n1 = is_numeric($number1) ? (float)$number1 : 0;
        $n2 = is_numeric($number2) ? (float)$number2 : 0;
        $result = $n1 + $n2;
        if (floor($result) == $result) {
            $result = (int)$result;
        }

        if (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json')) {
            Response::json(['number1' => $number1, 'number2' => $number2, 'sum' => $result]);
            return;
        }

        header('Content-Type: text/plain; charset=utf-8');
        echo (string)$result;
    }

    public function ok(): void {
        header('Content-Type: text/plain; charset=utf-8');
        echo "ok";
    }
}
