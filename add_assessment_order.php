<?php
require_once 'config/database.php';

$db = new Database();
$pdo = $db->getConnection();

try {
    echo "Adding assessment_order field to assessments table...\n";
    
    // Add the assessment_order column
    $sql = "ALTER TABLE `assessments` 
            ADD COLUMN `assessment_order` int(11) DEFAULT 1 COMMENT 'Manual order for assessments (1 = first, 2 = second, etc.)' 
            AFTER `assessment_title`";
    $pdo->exec($sql);
    echo "✓ Added assessment_order column\n";
    
    // Update existing assessments with default order values
    $updates = [
        ['id' => 'assess_68b6e5b2bd453', 'order' => 1, 'name' => 'Assessment 1'],
        ['id' => 'assess_68b63fb3c750e', 'order' => 2, 'name' => 'Assessment 2'],
        ['id' => 'assess_68b6f2db5e3d8', 'order' => 3, 'name' => 'Assessment 3']
    ];
    
    foreach ($updates as $update) {
        $stmt = $pdo->prepare("UPDATE `assessments` SET `assessment_order` = ? WHERE `id` = ?");
        $stmt->execute([$update['order'], $update['id']]);
        echo "✓ Updated {$update['name']} to order {$update['order']}\n";
    }
    
    // Add index for better performance
    $sql = "ALTER TABLE `assessments` ADD KEY `idx_assessment_order` (`assessment_order`)";
    $pdo->exec($sql);
    echo "✓ Added index for assessment_order\n";
    
    echo "\n🎉 Successfully added assessment_order field!\n";
    echo "Teachers can now manually set the order of assessments.\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
