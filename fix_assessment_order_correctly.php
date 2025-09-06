<?php
require_once 'config/database.php';

$db = new Database();
$pdo = $db->getConnection();

try {
    echo "Fixing assessment order correctly...\n\n";
    
    // Based on the database data, let's fix the order properly
    // assess_68b6e5b2bd453 = Assessment 1 (should be order 1)
    // assess_68b63fb3c750e = Assessment 2 (should be order 2)  
    // assess_68b6f2db5e3d8 = Assessment 3 (should be order 3)
    
    $updates = [
        ['id' => 'assess_68b6e5b2bd453', 'order' => 1, 'title' => 'Assessment 1'],
        ['id' => 'assess_68b63fb3c750e', 'order' => 2, 'title' => 'Assessment 2'], 
        ['id' => 'assess_68b6f2db5e3d8', 'order' => 3, 'title' => 'Assessment 3']
    ];
    
    foreach ($updates as $update) {
        $stmt = $pdo->prepare("UPDATE `assessments` SET `assessment_order` = ? WHERE `id` = ?");
        $stmt->execute([$update['order'], $update['id']]);
        echo "✓ Set {$update['title']} (ID: {$update['id']}) to order {$update['order']}\n";
    }
    
    echo "\nFinal verification...\n";
    $stmt = $pdo->prepare("SELECT id, assessment_title, assessment_order FROM assessments ORDER BY assessment_order");
    $stmt->execute();
    $assessments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo str_repeat("-", 70) . "\n";
    printf("%-30s %-20s %-10s\n", "ID", "Title", "Order");
    echo str_repeat("-", 70) . "\n";
    
    foreach ($assessments as $assessment) {
        printf("%-30s %-20s %-10s\n", 
            $assessment['id'],
            $assessment['assessment_title'],
            $assessment['assessment_order']
        );
    }
    echo str_repeat("-", 70) . "\n";
    
    echo "\n🎉 Assessment order is now correct!\n";
    echo "Assessment 1 (order 1) → Assessment 2 (order 2) → Assessment 3 (order 3)\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
