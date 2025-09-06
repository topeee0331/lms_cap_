<?php
require_once 'config/database.php';

$db = new Database();
$pdo = $db->getConnection();

try {
    echo "Assessment Order Management\n";
    echo str_repeat("=", 50) . "\n\n";
    
    // Show current order
    echo "Current Assessment Order:\n";
    $stmt = $pdo->prepare("SELECT id, assessment_title, assessment_order FROM assessments ORDER BY assessment_order");
    $stmt->execute();
    $assessments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo str_repeat("-", 60) . "\n";
    printf("%-30s %-20s %-10s\n", "ID", "Title", "Order");
    echo str_repeat("-", 60) . "\n";
    
    foreach ($assessments as $assessment) {
        printf("%-30s %-20s %-10s\n", 
            $assessment['id'],
            $assessment['assessment_title'],
            $assessment['assessment_order']
        );
    }
    echo str_repeat("-", 60) . "\n\n";
    
    // Example: Change order (you can modify this)
    echo "Example: Changing Assessment 2 to be first (order 1)...\n";
    
    // First, move current order 1 to order 2
    $stmt = $pdo->prepare("UPDATE assessments SET assessment_order = 2 WHERE assessment_order = 1");
    $stmt->execute();
    
    // Then set Assessment 2 to order 1
    $stmt = $pdo->prepare("UPDATE assessments SET assessment_order = 1 WHERE id = 'assess_68b63fb3c750e'");
    $stmt->execute();
    
    echo "✓ Assessment order changed!\n\n";
    
    // Show new order
    echo "New Assessment Order:\n";
    $stmt = $pdo->prepare("SELECT id, assessment_title, assessment_order FROM assessments ORDER BY assessment_order");
    $stmt->execute();
    $assessments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo str_repeat("-", 60) . "\n";
    printf("%-30s %-20s %-10s\n", "ID", "Title", "Order");
    echo str_repeat("-", 60) . "\n";
    
    foreach ($assessments as $assessment) {
        printf("%-30s %-20s %-10s\n", 
            $assessment['id'],
            $assessment['assessment_title'],
            $assessment['assessment_order']
        );
    }
    echo str_repeat("-", 60) . "\n\n";
    
    echo "🎉 Assessment order has been updated!\n";
    echo "Now Assessment 2 will be the first assessment students can take.\n";
    echo "Teachers can change these orders anytime by updating the assessment_order field.\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
