<?php
require_once 'config/database.php';

$db = new Database();
$pdo = $db->getConnection();

$module_id = 'mod_68b572d5b916e';

// Find the course that contains this module
$stmt = $pdo->prepare("
    SELECT c.*, c.teacher_id, u.first_name, u.last_name
    FROM courses c
    JOIN users u ON c.teacher_id = u.id
    WHERE JSON_SEARCH(c.modules, 'one', ?) IS NOT NULL
");
$stmt->execute([$module_id]);
$course = $stmt->fetch();

if ($course) {
    echo "<h2>Course Data:</h2>";
    echo "<pre>" . print_r($course, true) . "</pre>";
    
    // Parse modules and find the specific module
    $modules_data = json_decode($course['modules'], true);
    $module = null;
    
    foreach ($modules_data as $mod) {
        if ($mod['id'] === $module_id) {
            $module = $mod;
            break;
        }
    }
    
    if ($module) {
        echo "<h2>Module Data:</h2>";
        echo "<pre>" . print_r($module, true) . "</pre>";
        
        echo "<h2>Assessments in Module:</h2>";
        $assessments = $module['assessments'] ?? [];
        echo "<pre>" . print_r($assessments, true) . "</pre>";
        
        foreach ($assessments as $assessment) {
            echo "<h3>Assessment: " . ($assessment['assessment_title'] ?? 'No Title') . "</h3>";
            echo "<pre>" . print_r($assessment, true) . "</pre>";
        }
    } else {
        echo "Module not found in course data";
    }
} else {
    echo "Course not found";
}
?>
