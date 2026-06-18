<?php
require_once __DIR__ . "/includes/bootstrap.php";

$siteUrl = rtrim(app_base_url(), "/") . "/";
$siteTitle = "SmartAttend - QR Attendance System for Universities";
$siteDescription = "SmartAttend is a web-based QR code attendance verification system for universities using student matric number login, lecturer sessions, GPS checks, face verification, and attendance reports.";
$siteImage = rtrim(app_base_url(), "/") . "/assets/img/smartattend-og.svg";
$faqItems = [
    [
        "question" => "How does SmartAttend QR attendance work?",
        "answer" => "A lecturer creates a class attendance session, SmartAttend generates a unique QR code, and students scan it to open the verified attendance form.",
    ],
    [
        "question" => "Can students mark attendance with smartphones?",
        "answer" => "Yes. Students can use a smartphone browser to scan the QR code, log in with matric no., complete face verification, and submit attendance.",
    ],
    [
        "question" => "Does SmartAttend reduce attendance impersonation?",
        "answer" => "SmartAttend reduces impersonation by combining QR session tokens, student authentication, GPS venue checks, and face capture verification.",
    ],
    [
        "question" => "Can lecturers export attendance records?",
        "answer" => "Lecturers and administrators can review attendance records and export reports for academic documentation.",
    ],
];
$structuredData = [
    "@context" => "https://schema.org",
    "@graph" => [
        [
            "@type" => "WebSite",
            "name" => "SmartAttend",
            "url" => $siteUrl,
            "description" => $siteDescription,
            "inLanguage" => "en",
        ],
        [
            "@type" => "SoftwareApplication",
            "name" => "SmartAttend",
            "applicationCategory" => "EducationalApplication",
            "operatingSystem" => "Web",
            "url" => $siteUrl,
            "image" => $siteImage,
            "description" => $siteDescription,
            "featureList" => [
                "QR code attendance verification",
                "Lecturer attendance session management",
                "Student matric number login",
                "GPS venue verification",
                "Face capture verification",
                "Attendance analytics and CSV reports",
            ],
            "audience" => [
                "@type" => "EducationalAudience",
                "educationalRole" => "student",
            ],
        ],
        [
            "@type" => "FAQPage",
            "mainEntity" => array_map(function ($item) {
                return [
                    "@type" => "Question",
                    "name" => $item["question"],
                    "acceptedAnswer" => [
                        "@type" => "Answer",
                        "text" => $item["answer"],
                    ],
                ];
            }, $faqItems),
        ],
    ],
];

$dashboardLink = null;
if (isset($_SESSION["role"])) {
    if ($_SESSION["role"] === "student") {
        $dashboardLink = "student/dashboard.php";
    } elseif ($_SESSION["role"] === "lecturer") {
        $dashboardLink = "lecturer/dashboard.php";
    } elseif ($_SESSION["role"] === "admin") {
        $dashboardLink = "admin/dashboard.php";
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($siteTitle); ?></title>
    <meta name="description" content="<?php echo e($siteDescription); ?>">
    <meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1">
    <meta name="author" content="SmartAttend">
    <meta name="application-name" content="SmartAttend">
    <meta name="theme-color" content="#003b73">
    <meta name="keywords" content="QR attendance system, university attendance system, student attendance verification, lecturer attendance records, GPS attendance, face verification attendance, web based attendance system">
    <link rel="canonical" href="<?php echo e($siteUrl); ?>">
    <meta property="og:title" content="<?php echo e($siteTitle); ?>">
    <meta property="og:description" content="<?php echo e($siteDescription); ?>">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?php echo e($siteUrl); ?>">
    <meta property="og:site_name" content="SmartAttend">
    <meta property="og:image" content="<?php echo e($siteImage); ?>">
    <meta property="og:image:alt" content="SmartAttend QR attendance verification dashboard preview">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?php echo e($siteTitle); ?>">
    <meta name="twitter:description" content="<?php echo e($siteDescription); ?>">
    <meta name="twitter:image" content="<?php echo e($siteImage); ?>">
    <link rel="stylesheet" href="assets/css/style.css?v=home-professional-4">
    <script type="application/ld+json">
    <?php echo json_encode($structuredData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT); ?>
    </script>
</head>

<body class="home-page">
    <header class="home-header">
        <a class="home-brand" href="<?php echo e($siteUrl); ?>" aria-label="SmartAttend home">
            <img src="assets/img/smartattend-logo.svg" alt="SmartAttend logo">
            <div class="home-brand-text">
                <strong>SmartAttend</strong>
                <small>University attendance</small>
            </div>
        </a>

        <nav class="home-nav" aria-label="Primary navigation">
            <?php if ($dashboardLink) { ?>
                <a href="<?php echo e($dashboardLink); ?>" class="home-nav-link">Dashboard</a>
            <?php } else { ?>
                <a href="auth/login.php?login_as=student" class="home-nav-link">Student Login</a>
                <a href="auth/login.php?login_as=staff" class="home-nav-link">Lecturer Portal</a>
            <?php } ?>
        </nav>
    </header>

    <main class="home-shell">
        <section class="home-hero" aria-labelledby="home-title">
            <div class="home-copy-block">
                <p class="home-kicker">University Attendance Verification</p>
                <h1 id="home-title">A smarter attendance system for university classrooms.</h1>
                <p class="home-copy">
                    SmartAttend helps departments replace manual attendance sheets with verified QR
                    check-ins, student identity checks, and clean attendance records.
                </p>

                <div class="home-actions" aria-label="Account actions">
                    <a href="auth/login.php" class="home-primary">Access System</a>
                    <a href="auth/register.php" class="home-secondary">Create Account</a>
                </div>

                <div class="home-capabilities" aria-label="Core SmartAttend capabilities">
                    <span>QR sessions</span>
                    <span>Face verification</span>
                    <span>GPS radius check</span>
                    <span>CSV export</span>
                </div>
            </div>

            <aside class="home-overview-panel" aria-label="SmartAttend platform overview">
                <div class="home-overview-head">
                    <p>System overview</p>
                    <strong>Built for academic attendance workflows</strong>
                </div>

                <div class="home-module-grid">
                    <div>
                        <span>01</span>
                        <strong>Student access</strong>
                        <p>Matric no. login, course registration, attendance percentage, and history.</p>
                    </div>
                    <div>
                        <span>02</span>
                        <strong>Lecturer tools</strong>
                        <p>Course sessions, venue settings, attendance monitoring, and exports.</p>
                    </div>
                    <div>
                        <span>03</span>
                        <strong>Admin oversight</strong>
                        <p>User management, course records, audit logs, and system-wide reports.</p>
                    </div>
                </div>

                <div class="home-verification-stack">
                    <strong>Verification layers</strong>
                    <span>QR token</span>
                    <span>Face scan</span>
                    <span>GPS radius</span>
                    <span>Secure login</span>
                </div>
            </aside>
        </section>

        <section class="home-proof-strip" aria-label="System coverage">
            <div><strong>3 roles</strong><span>Student, lecturer, admin</span></div>
            <div><strong>Verified check-ins</strong><span>QR, face scan, and GPS confirmation</span></div>
            <div><strong>Reports</strong><span>Attendance history and exports</span></div>
        </section>

        <section class="home-workflow" aria-label="How SmartAttend works">
            <article>
                <span>01</span>
                <strong>Create session</strong>
                <p>Lecturers choose a course, capture venue GPS, and generate a session QR code.</p>
            </article>
            <article>
                <span>02</span>
                <strong>Verify student</strong>
                <p>Students scan the QR code, log in, complete face scan, and pass the location check.</p>
            </article>
            <article>
                <span>03</span>
                <strong>Review records</strong>
                <p>Attendance is saved instantly for lecturer dashboards, admin review, and CSV export.</p>
            </article>
        </section>
    </main>
</body>

</html>
