<?php
require_once 'config/database.php';

$db = new Database();
$pdo = $db->getConnection();

try {
    echo "Checking current assessments and their order...\n\n";
    
    $stmt = $pdo->prepare("SELECT id, assessment_title, assessment_order, created_at FROM assessments ORDER BY assessment_order, created_at");
    $stmt->execute();
    $assessments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($assessments)) {
        echo "No assessments found.\n";
    } else {
        echo "Current assessments:\n";
        echo str_repeat("-", 80) . "\n";
        printf("%-25s %-20s %-10s %-20s\n", "ID", "Title", "Order", "Created");
        echo str_repeat("-", 80) . "\n";
        
        foreach ($assessments as $assessment) {
            printf("%-25s %-20s %-10s %-20s\n", 
                substr($assessment['id'], 0, 24),
                substr($assessment['assessment_title'], 0, 19),
                $assessment['assessment_order'] ?? 'NULL',
                $assessment['created_at']
            );
        }
        echo str_repeat("-", 80) . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
