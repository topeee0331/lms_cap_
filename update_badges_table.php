<?php
/**
 * Database Migration: Add created_by field to badges table
 * This allows tracking which teacher created each badge
 */

require_once 'config/config.php';

try {
    // Check if created_by column already exists
    $stmt = $db->prepare("SHOW COLUMNS FROM badges LIKE 'created_by'");
    $stmt->execute();
    $column_exists = $stmt->fetch();
    
    if (!$column_exists) {
        // Add created_by column
        $db->exec("ALTER TABLE badges ADD COLUMN created_by INT(11) DEFAULT NULL AFTER points_value");
        
        // Add foreign key constraint
        $db->exec("ALTER TABLE badges ADD CONSTRAINT fk_badges_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL");
        
        echo "✅ Successfully added 'created_by' column to badges table\n";
    } else {
        echo "ℹ️  'created_by' column already exists in badges table\n";
    }
    
    // Check if is_active column exists, if not add it
    $stmt = $db->prepare("SHOW COLUMNS FROM badges LIKE 'is_active'");
    $stmt->execute();
    $is_active_exists = $stmt->fetch();
    
    if (!$is_active_exists) {
        $db->exec("ALTER TABLE badges ADD COLUMN is_active TINYINT(1) DEFAULT 1 AFTER created_by");
        echo "✅ Successfully added 'is_active' column to badges table\n";
    } else {
        echo "ℹ️  'is_active' column already exists in badges table\n";
    }
    
    echo "🎉 Database migration completed successfully!\n";
    
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
