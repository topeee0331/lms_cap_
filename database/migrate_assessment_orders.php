<?php
/**
 * Assessment Order Migration Script
 * 
 * This script ensures all assessments have proper sequential ordering
 * and fixes any duplicate or missing order numbers.
 */

require_once __DIR__ . '/../config/database.php';

$db = new Database();
$pdo = $db->getConnection();

echo "=== ASSESSMENT ORDER MIGRATION ===\n\n";

try {
    $pdo->beginTransaction();
    
    // Get all courses
    $stmt = $pdo->prepare('SELECT id, course_name FROM courses ORDER BY id');
    $stmt->execute();
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $total_fixed = 0;
    
    foreach ($courses as $course) {
        echo "Processing Course: {$course['course_name']} (ID: {$course['id']})\n";
        
        // Get all assessments for this course ordered by creation date
        $stmt = $pdo->prepare('
            SELECT id, assessment_title, assessment_order, created_at 
            FROM assessments 
            WHERE course_id = ? 
            ORDER BY created_at ASC
        ');
        $stmt->execute([$course['id']]);
        $assessments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($assessments)) {
            echo "  No assessments found.\n";
            continue;
        }
        
        // Check current order status
        $current_orders = array_column($assessments, 'assessment_order');
        $has_duplicates = count($current_orders) !== count(array_unique($current_orders));
        $has_gaps = false;
        
        if (!empty($current_orders)) {
            $min_order = min($current_orders);
            $max_order = max($current_orders);
            $expected_count = $max_order - $min_order + 1;
            $has_gaps = $expected_count !== count(array_unique($current_orders));
        }
        
        if (!$has_duplicates && !$has_gaps) {
            echo "  ✓ Order is already correct.\n";
            continue;
        }
        
        echo "  Issues found: " . ($has_duplicates ? "duplicates " : "") . ($has_gaps ? "gaps" : "") . "\n";
        
        // Fix ordering
        $new_order = 1;
        foreach ($assessments as $assessment) {
            $stmt = $pdo->prepare('UPDATE assessments SET assessment_order = ? WHERE id = ?');
            $stmt->execute([$new_order, $assessment['id']]);
            echo "    {$assessment['assessment_title']} -> Order {$new_order}\n";
            $new_order++;
            $total_fixed++;
        }
        
        echo "  ✓ Fixed " . count($assessments) . " assessments.\n\n";
    }
    
    $pdo->commit();
    
    echo "=== MIGRATION COMPLETE ===\n";
    echo "Total assessments fixed: {$total_fixed}\n";
    
    // Verify the fix
    echo "\n=== VERIFICATION ===\n";
    $stmt = $pdo->prepare('
        SELECT course_id, assessment_order, COUNT(*) as count 
        FROM assessments 
        GROUP BY course_id, assessment_order 
        HAVING COUNT(*) > 1
    ');
    $stmt->execute();
    $duplicates = $stmt->fetchAll();
    
    if (empty($duplicates)) {
        echo "✓ No duplicate orders found.\n";
    } else {
        echo "✗ Still have duplicate orders:\n";
        foreach($duplicates as $dup) {
            echo "  Course " . $dup['course_id'] . " has " . $dup['count'] . " assessments with order " . $dup['assessment_order'] . "\n";
        }
    }
    
    // Check for gaps
    $stmt = $pdo->prepare('SELECT course_id FROM assessments GROUP BY course_id');
    $stmt->execute();
    $courses = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $courses_with_gaps = 0;
    foreach($courses as $course_id) {
        $stmt = $pdo->prepare('SELECT assessment_order FROM assessments WHERE course_id = ? ORDER BY assessment_order');
        $stmt->execute([$course_id]);
        $orders = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $expected_order = 1;
        $has_gaps = false;
        
        foreach($orders as $order) {
            if ($order != $expected_order) {
                $has_gaps = true;
                break;
            }
            $expected_order++;
        }
        
        if ($has_gaps) {
            $courses_with_gaps++;
        }
    }
    
    if ($courses_with_gaps == 0) {
        echo "✓ No gaps in ordering found.\n";
    } else {
        echo "✗ {$courses_with_gaps} courses still have gaps in ordering.\n";
    }
    
} catch (Exception $e) {
    $pdo->rollback();
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\nMigration completed successfully!\n";
?>
