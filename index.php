<?php
require_once __DIR__ . "/includes/bootstrap.php";

$siteUrl = rtrim(app_base_url(), "/") . "/";
$siteTitle = "SmartAttend - QR Attendance System for Universities";
$siteDescription = "SmartAttend is a web-based QR code attendance verification system for universities using student matric number login, lecturer sessions, GPS checks, face verification, and attendance reports.";
$siteImage = rtrim(app_base_url(), "/") . "/assets/img/smartattend-og.svg";
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
    ],
];

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
    <link rel="icon" type="image/svg+xml" href="<?php echo e($siteUrl); ?>favicon.svg">
    <link rel="shortcut icon" type="image/svg+xml" href="<?php echo e($siteUrl); ?>favicon.svg">
    <link rel="stylesheet" href="assets/css/style.css?v=saas-landing-1">
    <script type="application/ld+json">
    <?php echo json_encode($structuredData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT); ?>
    </script>
</head>

<body class="saas-page">
    <header class="saas-navbar" data-nav>
        <div class="saas-nav-inner">
            <a class="saas-brand" href="<?php echo e($siteUrl); ?>" aria-label="SmartAttend home">
                <img src="assets/img/smartattend-logo.svg" alt="SmartAttend logo">
                <strong>SmartAttend</strong>
            </a>

            <nav class="saas-nav-links" aria-label="Landing navigation">
                <a href="#features">Features</a>
                <a href="#how-it-works">How it Works</a>
                <a href="#results">Results</a>
            </nav>

            <div class="saas-nav-actions">
                <a href="auth/login.php?login_as=student" class="saas-btn saas-btn-ghost">Student Login</a>
                <a href="auth/login.php?login_as=staff" class="saas-btn saas-btn-primary">Lecturer Portal</a>
            </div>
        </div>
    </header>

    <main>
        <section class="saas-hero saas-section" data-reveal>
            <div class="saas-container saas-hero-grid">
                <div class="saas-hero-copy">
                    <p class="saas-badge">&#10003; Trusted University Attendance Platform</p>
                    <h1>Attendance tracking made effortless.</h1>
                    <p>
                        SmartAttend replaces paper attendance sheets with QR check-ins,
                        face verification and GPS validation &mdash; all in one secure platform.
                    </p>
                    <div class="saas-hero-actions">
                        <a href="auth/login.php?login_as=student" class="saas-btn saas-btn-primary">Student Login &rarr;</a>
                        <a href="auth/login.php?login_as=staff" class="saas-btn saas-btn-secondary">Lecturer Portal</a>
                    </div>
                    <small>Used by lecturers, students and institutions.</small>
                </div>

                <aside class="saas-dashboard" aria-label="SmartAttend dashboard preview">
                    <div class="saas-dashboard-top">
                        <div>
                            <span></span><span></span><span></span>
                        </div>
                        <strong>Attendance Overview</strong>
                    </div>
                    <div class="saas-dashboard-grid">
                        <div class="saas-progress-card">
                            <div class="saas-ring" style="--value: 86;">
                                <span>86%</span>
                            </div>
                            <p>Attendance Percentage</p>
                        </div>
                        <div class="saas-metric">
                            <span>Active Sessions</span>
                            <strong>12</strong>
                            <em>+4 this week</em>
                        </div>
                        <div class="saas-metric">
                            <span>Students Present</span>
                            <strong>438</strong>
                            <em class="is-success">Verified</em>
                        </div>
                        <div class="saas-metric">
                            <span>QR Session Status</span>
                            <strong>Online</strong>
                            <em class="is-success">Stable</em>
                        </div>
                    </div>
                    <div class="saas-chart" aria-hidden="true">
                        <span style="height: 42%"></span>
                        <span style="height: 68%"></span>
                        <span style="height: 54%"></span>
                        <span style="height: 82%"></span>
                        <span style="height: 74%"></span>
                        <span style="height: 90%"></span>
                    </div>
                    <div class="saas-table">
                        <div><span>Recent activity</span><span>Result</span></div>
                        <div><strong>Face verification</strong><em>Passed</em></div>
                        <div><strong>Location check</strong><em>Verified</em></div>
                    </div>
                </aside>
            </div>
        </section>

        <section class="saas-section" id="features" data-reveal>
            <div class="saas-container">
                <div class="saas-section-heading">
                    <span>Features</span>
                    <h2>Everything needed for verified attendance.</h2>
                </div>
                <div class="saas-feature-grid">
                    <article>
                        <i>QR</i>
                        <h3>QR Attendance</h3>
                        <p>Generate session QR codes for instant attendance.</p>
                    </article>
                    <article>
                        <i>AI</i>
                        <h3>Face Verification</h3>
                        <p>Prevent impersonation with AI face verification.</p>
                    </article>
                    <article>
                        <i>GPS</i>
                        <h3>GPS Validation</h3>
                        <p>Ensure students are physically within approved class locations.</p>
                    </article>
                </div>
            </div>
        </section>

        <section class="saas-section" id="how-it-works" data-reveal>
            <div class="saas-container">
                <div class="saas-section-heading">
                    <span>How it works</span>
                    <h2>From session setup to export in three steps.</h2>
                </div>
                <div class="saas-timeline">
                    <article>
                        <i>1</i>
                        <span>Step 1</span>
                        <strong>Lecturer creates attendance session.</strong>
                    </article>
                    <article>
                        <i>2</i>
                        <span>Step 2</span>
                        <strong>Students scan QR and verify identity.</strong>
                    </article>
                    <article>
                        <i>3</i>
                        <span>Step 3</span>
                        <strong>Attendance is automatically recorded and exported.</strong>
                    </article>
                </div>
            </div>
        </section>

        <section class="saas-stats saas-section" id="results" data-reveal>
            <div class="saas-container saas-stats-grid">
                <div><strong data-count="98" data-suffix="%">0%</strong><span>Attendance Accuracy</span></div>
                <div><strong data-count="50" data-suffix="K+">0K+</strong><span>Attendance Records</span></div>
                <div><strong data-count="500" data-suffix="+">0+</strong><span>Sessions Conducted</span></div>
                <div><strong data-count="99.9" data-suffix="%">0%</strong><span>System Uptime</span></div>
            </div>
        </section>

        <section class="saas-cta saas-section" id="access" data-reveal>
            <div class="saas-container">
                <h2>Ready to modernize classroom attendance?</h2>
                <p>Join institutions using SmartAttend to simplify attendance management.</p>
                <div>
                    <a href="auth/login.php?login_as=student" class="saas-btn saas-btn-light">Student Login</a>
                    <a href="auth/login.php?login_as=staff" class="saas-btn saas-btn-dark-ghost">Lecturer Portal</a>
                </div>
            </div>
        </section>
    </main>

    <footer class="saas-footer">
        <div class="saas-container saas-footer-grid">
            <div>
                <strong>SmartAttend</strong>
                <a href="<?php echo e($siteUrl); ?>">University attendance system</a>
                <a href="#access">Access portal</a>
            </div>
            <div>
                <strong>Product</strong>
                <a href="#features">Features</a>
                <a href="#how-it-works">How it Works</a>
                <a href="#results">Results</a>
            </div>
            <div>
                <strong>Access</strong>
                <a href="auth/login.php?login_as=student">Student Login</a>
                <a href="auth/login.php?login_as=staff">Lecturer Portal</a>
                <a href="auth/register.php">Create Account</a>
            </div>
            <div>
                <strong>Project</strong>
                <a href="#features">QR verification</a>
                <a href="#features">Face and GPS checks</a>
                <a href="#results">Attendance reports</a>
            </div>
        </div>
        <p>&copy; SmartAttend 2026</p>
    </footer>

    <script>
    (function () {
        var navbar = document.querySelector("[data-nav]");
        var revealItems = document.querySelectorAll("[data-reveal]");
        var counters = document.querySelectorAll("[data-count]");

        function handleScroll() {
            if (navbar) {
                navbar.classList.toggle("is-scrolled", window.scrollY > 16);
            }
        }

        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting) {
                    return;
                }
                entry.target.classList.add("is-visible");
                entry.target.querySelectorAll("[data-count]").forEach(function (counter) {
                    if (counter.dataset.done) {
                        return;
                    }
                    counter.dataset.done = "true";
                    var target = parseFloat(counter.dataset.count || "0");
                    var suffix = counter.dataset.suffix || "";
                    var start = performance.now();
                    function tick(now) {
                        var progress = Math.min((now - start) / 1100, 1);
                        var eased = 1 - Math.pow(1 - progress, 3);
                        var value = target * eased;
                        counter.textContent = (target % 1 ? value.toFixed(1) : Math.round(value)) + suffix;
                        if (progress < 1) {
                            requestAnimationFrame(tick);
                        }
                    }
                    requestAnimationFrame(tick);
                });
                observer.unobserve(entry.target);
            });
        }, { threshold: 0.18 });

        revealItems.forEach(function (item) {
            observer.observe(item);
        });

        window.addEventListener("scroll", handleScroll, { passive: true });
        handleScroll();
    })();
    </script>
</body>

</html>
