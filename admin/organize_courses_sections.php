<?php
session_start();
require_once '../config/database.php';
require_once '../config/config.php';

// Admin-only tool
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo 'Access denied';
    exit;
}

// Helpers
function json_decode_array($json) {
    if (!$json) return [];
    $decoded = json_decode($json, true);
    return is_array($decoded) ? $decoded : [];
}

function array_values_unique_sorted(array $values) {
    $unique = array_values(array_unique($values));
    sort($unique, SORT_NUMERIC);
    return $unique;
}

function get_majority_year_level_from_sections(PDO $db, array $section_ids) {
    if (empty($section_ids)) return null;
    $placeholders = implode(',', array_fill(0, count($section_ids), '?'));
    $stmt = $db->prepare("SELECT year_level, COUNT(*) as cnt FROM sections WHERE id IN ($placeholders) GROUP BY year_level ORDER BY cnt DESC");
    $stmt->execute($section_ids);
    $row = $stmt->fetch();
    return $row ? (int) $row['year_level'] : null;
}

$action = $_SERVER['REQUEST_METHOD'] === 'POST' ? 'apply' : 'dry_run';

// Load active, non-archived sections mapped by academic period and year level
$sections_by_period_year = [];
$sections_stmt = $db->query("SELECT id, academic_period_id, year_level FROM sections WHERE is_active = 1 AND (is_archived = 0 OR is_archived IS NULL)");
$sections = $sections_stmt ? $sections_stmt->fetchAll() : [];
foreach ($sections as $s) {
    $ap = (int) $s['academic_period_id'];
    $yl = (int) $s['year_level'];
    if (!isset($sections_by_period_year[$ap])) $sections_by_period_year[$ap] = [];
    if (!isset($sections_by_period_year[$ap][$yl])) $sections_by_period_year[$ap][$yl] = [];
    $sections_by_period_year[$ap][$yl][] = (int) $s['id'];
}

// Fetch non-archived courses
$courses_stmt = $db->query("SELECT id, course_name, course_code, academic_period_id, year_level, sections FROM courses WHERE is_archived = 0");
$courses = $courses_stmt ? $courses_stmt->fetchAll() : [];

$changes = [
    'courses_with_null_year_level' => [],
    'year_level_updates' => [], // course_id => new_year_level
    'section_replacements' => [], // course_id => ['from' => [...], 'to' => [...]]
    'skipped' => []
];

foreach ($courses as $course) {
    $course_id = (int) $course['id'];
    $ap_id = (int) $course['academic_period_id'];
    $current_year_level = $course['year_level'] !== null ? (int) $course['year_level'] : null;
    $current_sections = array_map('intval', json_decode_array($course['sections']));

    // Determine target year level
    $target_year_level = $current_year_level;
    if ($target_year_level === null) {
        // Infer from current sections (majority), else default to 1
        $inferred = get_majority_year_level_from_sections($db, $current_sections);
        $target_year_level = $inferred !== null ? $inferred : 1;
        $changes['courses_with_null_year_level'][] = $course_id;
        $changes['year_level_updates'][$course_id] = $target_year_level;
    }

    // Compute target sections: same year, same academic period, active/not archived
    $target_sections = $sections_by_period_year[$ap_id][$target_year_level] ?? [];

    // Replace set with target (within same period/year)
    $from = array_values_unique_sorted($current_sections);
    $to = array_values_unique_sorted($target_sections);

    // Record change if different
    if ($from !== $to || isset($changes['year_level_updates'][$course_id])) {
        $changes['section_replacements'][$course_id] = [
            'name' => $course['course_name'],
            'code' => $course['course_code'],
            'academic_period_id' => $ap_id,
            'year_level' => $target_year_level,
            'from' => $from,
            'to' => $to
        ];
    }
}

if ($action === 'dry_run') {
    // Render a simple admin page summarizing planned changes
    ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Organize Courses by Year Level and Academic Period (Dry Run)</title>
    <link href="../assets/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="p-4">
    <div class="container">
        <h3 class="mb-3"><i class="bi bi-gear me-2"></i>Organize Courses by Year Level and Academic Period</h3>
        <div class="alert alert-info">
            This is a dry run. Review changes below. Click Apply to perform updates.
        </div>
        <ul class="list-group mb-3">
            <li class="list-group-item d-flex justify-content-between align-items-center">
                Courses with NULL year_level to set
                <span class="badge bg-primary rounded-pill"><?php echo count($changes['courses_with_null_year_level']); ?></span>
            </li>
            <li class="list-group-item d-flex justify-content-between align-items-center">
                Courses with section assignments to replace
                <span class="badge bg-primary rounded-pill"><?php echo count($changes['section_replacements']); ?></span>
            </li>
        </ul>

        <div class="accordion mb-4" id="changesAccordion">
            <div class="accordion-item">
                <h2 class="accordion-header" id="headingOne">
                    <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne">
                        Section assignment changes (<?php echo count($changes['section_replacements']); ?>)
                    </button>
                </h2>
                <div id="collapseOne" class="accordion-collapse collapse show" data-bs-parent="#changesAccordion">
                    <div class="accordion-body">
                        <?php if (empty($changes['section_replacements'])): ?>
                            <div class="text-muted">No changes.</div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm table-striped">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Course</th>
                                            <th>AP</th>
                                            <th>Year</th>
                                            <th>From</th>
                                            <th>To</th>
                                            <th>Year set?</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($changes['section_replacements'] as $cid => $info): ?>
                                            <tr>
                                                <td><?php echo (int) $cid; ?></td>
                                                <td><?php echo htmlspecialchars(($info['name'] ?? '') . ' (' . ($info['code'] ?? '') . ')'); ?></td>
                                                <td><?php echo (int) ($info['academic_period_id'] ?? 0); ?></td>
                                                <td><?php echo (int) ($info['year_level'] ?? 0); ?></td>
                                                <td><code><?php echo htmlspecialchars(json_encode($info['from'])); ?></code></td>
                                                <td><code><?php echo htmlspecialchars(json_encode($info['to'])); ?></code></td>
                                                <td><?php echo isset($changes['year_level_updates'][$cid]) ? '<span class="badge bg-warning">yes</span>' : '<span class="badge bg-secondary">no</span>'; ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <form method="post">
            <button type="submit" class="btn btn-danger"><i class="bi bi-check2-circle me-1"></i>Apply Changes</button>
            <a href="sections.php" class="btn btn-secondary ms-2"><i class="bi bi-arrow-left me-1"></i>Back to Sections</a>
        </form>
    </div>
    <script src="../assets/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
<?php
    exit;
}

// Apply changes
try {
    $db->beginTransaction();

    // Update year levels where needed
    foreach ($changes['year_level_updates'] as $course_id => $new_year_level) {
        $stmt = $db->prepare("UPDATE courses SET year_level = ? WHERE id = ?");
        $stmt->execute([(int) $new_year_level, (int) $course_id]);
    }

    // Replace sections with exact target list
    foreach ($changes['section_replacements'] as $course_id => $info) {
        $to_json = json_encode(array_values($info['to']));
        $stmt = $db->prepare("UPDATE courses SET sections = ? WHERE id = ?");
        $stmt->execute([$to_json, (int) $course_id]);
    }

    $db->commit();

    $_SESSION['flash_success'] = 'Course-section organization completed successfully.';
    header('Location: organize_courses_sections.php');
    exit;
} catch (Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    $_SESSION['flash_error'] = 'Failed to apply changes: ' . $e->getMessage();
    header('Location: organize_courses_sections.php');
    exit;
}
?>


