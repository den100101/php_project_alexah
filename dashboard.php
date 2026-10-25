<?php
require_once __DIR__ . "/db.php";
require_once __DIR__ . "/auth.php";
require_once __DIR__ . "/layout.php";

require_login();

$username = $_SESSION['USERNAME'] ?? 'User';
$role = strtolower(current_usertype());

/*
 * Count records using fixed, trusted table names.
 */
function dashboard_count(mysqli $conn, string $table): int
{
    $allowedTables = [
        'tbl_course',
        'tbl_instructors',
        'tbl_students',
        'tbl_section',
        'tbl_term',
        'tbl_schoolyear',
        'tbl_yearlevel',
        'tbl_account'
    ];

    if (!in_array($table, $allowedTables, true)) {
        return 0;
    }

    try {
        $result = $conn->query("SELECT COUNT(*) AS total FROM `$table`");

        if ($result) {
            $row = $result->fetch_assoc();
            return (int)($row['total'] ?? 0);
        }
    } catch (Throwable $e) {
        error_log("Dashboard count failed: " . $e->getMessage());
    }

    return 0;
}

/*
 * Only show statistics relevant to the current user's access.
 */
$allStats = [
    [
        'label' => 'Total Courses',
        'table' => 'tbl_course',
        'page' => 'subjects',
        'description' => 'Registered academic courses',
        'icon' => '📚'
    ],
    [
        'label' => 'Instructors',
        'table' => 'tbl_instructors',
        'page' => 'instructors',
        'description' => 'Instructor records',
        'icon' => '👨‍🏫'
    ],
    [
        'label' => 'Students',
        'table' => 'tbl_students',
        'page' => null,
        'description' => 'Registered student records',
        'icon' => '🎓'
    ],
    [
        'label' => 'Sections',
        'table' => 'tbl_section',
        'page' => 'section',
        'description' => 'Available class sections',
        'icon' => '🏫'
    ],
    [
        'label' => 'Terms',
        'table' => 'tbl_term',
        'page' => 'term',
        'description' => 'Academic terms',
        'icon' => '📅'
    ],
    [
        'label' => 'School Years',
        'table' => 'tbl_schoolyear',
        'page' => 'schoolyear',
        'description' => 'Configured school years',
        'icon' => '🗓️'
    ],
    [
        'label' => 'Year Levels',
        'table' => 'tbl_yearlevel',
        'page' => 'yearlevel',
        'description' => 'Configured year levels',
        'icon' => '📖'
    ],
];

$stats = [];

foreach ($allStats as $stat) {
    if ($stat['page'] !== null && !can_access($stat['page'])) {
        continue;
    }

    // Students should only see course-related statistics.
    if ($role === 'student' && $stat['table'] !== 'tbl_course') {
        continue;
    }

    $stat['count'] = dashboard_count($conn, $stat['table']);
    $stats[] = $stat;
}

/*
 * Retrieve recent records from tables that have known columns.
 */
$recentCourses = [];
$recentInstructors = [];

try {
    if (can_access('subjects')) {
        $result = $conn->query(
            "SELECT ID, COURSE_CODE, COURSE_NAME, UNITS
             FROM tbl_course
             ORDER BY ID DESC
             LIMIT 5"
        );

        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $recentCourses[] = $row;
            }
        }
    }
} catch (Throwable $e) {
    error_log("Dashboard courses retrieval failed: " . $e->getMessage());
}

try {
    if (can_access('instructors')) {
        $result = $conn->query(
            "SELECT ID, SALUTATION, FIRSTNAME, MIDDLENAME, LASTNAME
             FROM tbl_instructors
             ORDER BY ID DESC
             LIMIT 5"
        );

        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $recentInstructors[] = $row;
            }
        }
    }
} catch (Throwable $e) {
    error_log("Dashboard instructors retrieval failed: " . $e->getMessage());
}

$quickLinks = [];

foreach (PAGES as $key => $info) {
    if (can_access($key)) {
        $quickLinks[] = [
            'key' => $key,
            'title' => $info[0],
            'url' => $info[1]
        ];
    }
}

page_header('Dashboard', 'dashboard');
?>

<section class="dashboard-hero">
    <div class="dashboard-hero-content">
        <span class="dashboard-eyebrow">SCHOOL MANAGEMENT SYSTEM</span>

        <h1>Welcome back, <?php echo h($username); ?>!</h1>

        <p>
            Manage your academic records and access your available
            school management tools from one place.
        </p>

        <div class="dashboard-user-badges">
            <span class="role-badge">
                <?php echo h(ucfirst($role)); ?> Account
            </span>

            <span class="dashboard-date">
                <?php echo date('F j, Y'); ?>
            </span>
        </div>
    </div>

    <div class="dashboard-hero-icon" aria-hidden="true">
        🎓
    </div>
</section>

<section class="dashboard-section">
    <div class="section-heading">
        <div>
            <span class="section-eyebrow">OVERVIEW</span>
            <h2>Academic Summary</h2>
            <p>Your available academic records at a glance.</p>
        </div>
    </div>

    <div class="dashboard-stats">
        <?php foreach ($stats as $stat): ?>
            <article class="stat-card">
                <div class="stat-card-top">
                    <span class="stat-icon">
                        <?php echo h($stat['icon']); ?>
                    </span>

                    <span class="stat-label">
                        <?php echo h($stat['label']); ?>
                    </span>
                </div>

                <div class="stat-number">
                    <?php echo number_format($stat['count']); ?>
                </div>

                <p><?php echo h($stat['description']); ?></p>

                <?php if ($stat['page'] !== null && can_access($stat['page'])): ?>
                    <a
                        class="stat-link"
                        href="<?php
                                foreach (PAGES as $key => $info) {
                                    if ($key === $stat['page']) {
                                        echo h($info[1]);
                                        break;
                                    }
                                }
                                ?>">
                        View records <span aria-hidden="true">→</span>
                    </a>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<section class="dashboard-section">
    <div class="section-heading">
        <div>
            <span class="section-eyebrow">NAVIGATION</span>
            <h2>Quick Access</h2>
            <p>Open a module available to your account.</p>
        </div>
    </div>

    <div class="dashboard-quick-links">
        <?php foreach ($quickLinks as $link): ?>
            <a
                href="<?php echo h($link['url']); ?>"
                class="quick-link-card">
                <span class="quick-link-icon" aria-hidden="true">
                    <?php
                    $icons = [
                        'subjects' => '📚',
                        'term' => '📅',
                        'instructors' => '👨‍🏫',
                        'schoolyear' => '🗓️',
                        'section' => '🏫',
                        'yearlevel' => '📖'
                    ];

                    echo h($icons[$link['key']] ?? '📁');
                    ?>
                </span>

                <span class="quick-link-text">
                    <strong><?php echo h($link['title']); ?></strong>
                    <small>Open module</small>
                </span>

                <span class="quick-link-arrow" aria-hidden="true">→</span>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<?php if (can_access('subjects') || can_access('instructors')): ?>
    <section class="dashboard-section">
        <div class="section-heading">
            <div>
                <span class="section-eyebrow">RECENT RECORDS</span>
                <h2>Recently Added</h2>
                <p>The latest course and instructor records by ID.</p>
            </div>
        </div>

        <div class="dashboard-recent-grid">
            <?php if (can_access('subjects')): ?>
                <article class="dashboard-panel">
                    <div class="panel-heading">
                        <h3>Recent Courses</h3>
                        <a href="subjects.php">View all →</a>
                    </div>

                    <?php if (empty($recentCourses)): ?>
                        <p class="empty-state">No course records found.</p>
                    <?php else: ?>
                        <div class="table-container">
                            <table class="dashboard-table">
                                <thead>
                                    <tr>
                                        <th>Code</th>
                                        <th>Course Name</th>
                                        <th>Units</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentCourses as $course): ?>
                                        <tr>
                                            <td>
                                                <span class="course-code">
                                                    <?php echo h($course['COURSE_CODE']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo h($course['COURSE_NAME']); ?></td>
                                            <td><?php echo (int)$course['UNITS']; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </article>
            <?php endif; ?>

            <?php if (can_access('instructors')): ?>
                <article class="dashboard-panel">
                    <div class="panel-heading">
                        <h3>Recent Instructors</h3>
                        <a href="instructors.php">View all →</a>
                    </div>

                    <?php if (empty($recentInstructors)): ?>
                        <p class="empty-state">No instructor records found.</p>
                    <?php else: ?>
                        <div class="recent-person-list">
                            <?php foreach ($recentInstructors as $instructor): ?>
                                <?php
                                $fullName = trim(
                                    $instructor['SALUTATION'] . ' ' .
                                        $instructor['FIRSTNAME'] . ' ' .
                                        ($instructor['MIDDLENAME'] ?? '') . ' ' .
                                        $instructor['LASTNAME']
                                );

                                $initial = strtoupper(
                                    substr($instructor['FIRSTNAME'], 0, 1)
                                );
                                ?>
                                <div class="recent-person">
                                    <span class="recent-person-avatar">
                                        <?php echo h($initial); ?>
                                    </span>

                                    <span class="recent-person-info">
                                        <strong><?php echo h($fullName); ?></strong>
                                        <small>
                                            Instructor ID:
                                            <?php echo (int)$instructor['ID']; ?>
                                        </small>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </article>
            <?php endif; ?>
        </div>
    </section>
<?php endif; ?>

<footer class="dashboard-footer">
    <span>School Management System</span>
    <span>Signed in as <?php echo h(ucfirst($role)); ?></span>
</footer>

<?php page_footer(); ?>