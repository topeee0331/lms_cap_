<?php
require_once 'config/database.php';

$db = new Database();
$pdo = $db->getConnection();

try {
    echo "Fixing assessment order to match titles...\n\n";
    
    // Fix the order to match the assessment titles
    $updates = [
        ['id' => 'assess_68b6e5b2bd453', 'order' => 1, 'title' => 'Assessment 1'],
        ['id' => 'assess_68b63fb3c750e', 'order' => 2, 'title' => 'Assessment 2'], 
        ['id' => 'assess_68b6f2db5e3d8', 'order' => 3, 'title' => 'Assessment 3']
    ];
    
    foreach ($updates as $update) {
        $stmt = $pdo->prepare("UPDATE `assessments` SET `assessment_order` = ? WHERE `id` = ?");
        $stmt->execute([$update['order'], $update['id']]);
        echo "✓ Updated {$update['title']} to order {$update['order']}\n";
    }
    
    echo "\nVerifying the changes...\n";
    $stmt = $pdo->prepare("SELECT id, assessment_title, assessment_order FROM assessments ORDER BY assessment_order");
    $stmt->execute();
    $assessments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo str_repeat("-", 60) . "\n";
    printf("%-25s %-20s %-10s\n", "ID", "Title", "Order");
    echo str_repeat("-", 60) . "\n";
    
    foreach ($assessments as $assessment) {
        printf("%-25s %-20s %-10s\n", 
            substr($assessment['id'], 0, 24),
            $assessment['assessment_title'],
            $assessment['assessment_order']
        );
    }
    echo str_repeat("-", 60) . "\n";
    
    echo "\n🎉 Assessment order has been fixed!\n";
    echo "Assessment 1 is now order 1, Assessment 2 is order 2, etc.\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
