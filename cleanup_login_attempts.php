<?php
/**
 * Cleanup script for login_attempts table
 * Removes old records to maintain performance
 * Run this script periodically (e.g., daily via cron)
 */

require_once 'config/config.php';
require_once 'config/database.php';

// Configuration
$cleanup_days = 30; // Remove records older than 30 days
$batch_size = 1000; // Process in batches to avoid memory issues

echo "<h2>Login Attempts Cleanup Script</h2>";
echo "<p>This script removes login attempt records older than {$cleanup_days} days.</p>";

try {
    // Get current table size
    $stmt = $pdo->query("SELECT COUNT(*) as total_records FROM login_attempts");
    $total_before = $stmt->fetch()['total_records'];
    
    echo "<p><strong>Records before cleanup:</strong> {$total_before}</p>";
    
    // Calculate cutoff date
    $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$cleanup_days} days"));
    echo "<p><strong>Removing records older than:</strong> {$cutoff_date}</p>";
    
    // Count records to be deleted
    $stmt = $pdo->prepare("SELECT COUNT(*) as to_delete FROM login_attempts WHERE attempt_time < ?");
    $stmt->execute([$cutoff_date]);
    $to_delete = $stmt->fetch()['to_delete'];
    
    echo "<p><strong>Records to be deleted:</strong> {$to_delete}</p>";
    
    if ($to_delete > 0) {
        // Delete in batches to avoid memory issues
        $deleted_total = 0;
        $offset = 0;
        
        while ($deleted_total < $to_delete) {
            $stmt = $pdo->prepare("
                DELETE FROM login_attempts 
                WHERE attempt_time < ? 
                ORDER BY attempt_time ASC 
                LIMIT ?
            ");
            $stmt->execute([$cutoff_date, $batch_size]);
            
            $deleted_batch = $stmt->rowCount();
            $deleted_total += $deleted_batch;
            
            echo "<p>Deleted batch: {$deleted_batch} records (Total: {$deleted_total})</p>";
            
            if ($deleted_batch < $batch_size) {
                break; // No more records to delete
            }
            
            // Small delay to prevent overwhelming the database
            usleep(100000); // 0.1 second
        }
        
        echo "<p style='color: green;'>✓ Cleanup completed successfully!</p>";
        echo "<p><strong>Total records deleted:</strong> {$deleted_total}</p>";
        
    } else {
        echo "<p style='color: blue;'>No old records found to delete.</p>";
    }
    
    // Get final table size
    $stmt = $pdo->query("SELECT COUNT(*) as total_records FROM login_attempts");
    $total_after = $stmt->fetch()['total_records'];
    
    echo "<p><strong>Records after cleanup:</strong> {$total_after}</p>";
    
    // Show table statistics
    echo "<h3>Table Statistics</h3>";
    
    // Recent activity (last 24 hours)
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total_24h,
            SUM(CASE WHEN success = 1 THEN 1 ELSE 0 END) as successful_24h,
            SUM(CASE WHEN success = 0 THEN 1 ELSE 0 END) as failed_24h
        FROM login_attempts 
        WHERE attempt_time > DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ");
    $stats_24h = $stmt->fetch();
    
    echo "<p><strong>Last 24 hours:</strong></p>";
    echo "<ul>";
    echo "<li>Total attempts: " . ($stats_24h['total_24h'] ?? 0) . "</li>";
    echo "<li>Successful: " . ($stats_24h['successful_24h'] ?? 0) . "</li>";
    echo "<li>Failed: " . ($stats_24h['failed_24h'] ?? 0) . "</li>";
    echo "</ul>";
    
    // Top IP addresses with failed attempts
    $stmt = $pdo->query("
        SELECT 
            ip_address,
            COUNT(*) as failed_count
        FROM login_attempts 
        WHERE success = 0 
        AND attempt_time > DATE_SUB(NOW(), INTERVAL 24 HOUR)
        GROUP BY ip_address 
        ORDER BY failed_count DESC 
        LIMIT 5
    ");
    $top_failed_ips = $stmt->fetchAll();
    
    if (!empty($top_failed_ips)) {
        echo "<p><strong>Top IP addresses with failed attempts (24h):</strong></p>";
        echo "<ul>";
        foreach ($top_failed_ips as $ip) {
            echo "<li>{$ip['ip_address']}: {$ip['failed_count']} failed attempts</li>";
        }
        echo "</ul>";
    }
    
    // Database optimization
    echo "<h3>Database Optimization</h3>";
    
    // Analyze table
    $pdo->exec("ANALYZE TABLE login_attempts");
    echo "<p>✓ Table analyzed for optimization</p>";
    
    // Show table size
    $stmt = $pdo->query("
        SELECT 
            ROUND(((data_length + index_length) / 1024 / 1024), 2) AS 'Size (MB)'
        FROM information_schema.tables 
        WHERE table_schema = DATABASE() 
        AND table_name = 'login_attempts'
    ");
    $table_size = $stmt->fetch();
    
    if ($table_size) {
        echo "<p><strong>Current table size:</strong> {$table_size['Size (MB)']} MB</p>";
    }
    
    echo "<h3>Cleanup Complete!</h3>";
    echo "<p>The login_attempts table has been cleaned and optimized.</p>";
    
    // Recommendations
    echo "<h3>Recommendations</h3>";
    echo "<ul>";
    echo "<li><strong>Schedule:</strong> Run this script daily via cron job</li>";
    echo "<li><strong>Monitoring:</strong> Check for unusual patterns in failed attempts</li>";
    echo "<li><strong>Backup:</strong> Ensure database backups include this table</li>";
    echo "<li><strong>Alerts:</strong> Set up alerts for high failure rates</li>";
    echo "</ul>";
    
    // Cron job example
    echo "<h3>Cron Job Example</h3>";
    echo "<pre># Run cleanup daily at 2 AM
0 2 * * * /usr/bin/php /path/to/your/lms_cap/cleanup_login_attempts.php > /dev/null 2>&1</pre>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Error during cleanup: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>Please check your database connection and try again.</p>";
}

echo "<hr>";
echo "<p><a href='test_login_throttling.php'>Test Login Throttling</a> | ";
echo "<a href='setup_login_throttling.php'>Setup Script</a> | ";
echo "<a href='login.php'>Login Page</a></p>";
?>
