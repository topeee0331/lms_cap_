<?php
require_once 'config/config.php';

echo "<h2>Questions Database Diagnostic</h2>";

try {
    // Test database connection
    echo "<h3>1. Database Connection Test</h3>";
    $stmt = $db->query("SELECT 1");
    echo "✅ Database connection successful<br>";
    
    // Check database version
    $stmt = $db->query("SELECT VERSION()");
    $version = $stmt->fetchColumn();
    echo "✅ Database version: " . $version . "<br>";
    
    // Check if questions table exists
    echo "<h3>2. Table Existence Check</h3>";
    $stmt = $db->query("SHOW TABLES LIKE 'questions'");
    if ($stmt->rowCount() > 0) {
        echo "✅ Questions table exists<br>";
    } else {
        echo "❌ Questions table does not exist!<br>";
        echo "Creating questions table...<br>";
        
        $create_table_sql = "
        CREATE TABLE `questions` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `assessment_id` varchar(50) NOT NULL COMMENT 'String ID like assess_68b63fb3c750e',
          `question_text` text NOT NULL,
          `question_type` varchar(32) NOT NULL DEFAULT 'multiple_choice',
          `question_order` int(11) NOT NULL,
          `points` int(11) DEFAULT 1,
          `options` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'JSON array of question options',
          `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
          `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
          PRIMARY KEY (`id`),
          KEY `assessment_id` (`assessment_id`),
          KEY `question_order` (`question_order`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $db->exec($create_table_sql);
        echo "✅ Questions table created successfully<br>";
    }
    
    // Check table structure
    echo "<h3>3. Table Structure Check</h3>";
    $stmt = $db->query("DESCRIBE questions");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    foreach ($columns as $column) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($column['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Key']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Default'] ?? 'NULL') . "</td>";
        echo "<td>" . htmlspecialchars($column['Extra']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Check current question count
    echo "<h3>4. Current Data Check</h3>";
    $stmt = $db->query("SELECT COUNT(*) as count FROM questions");
    $count = $stmt->fetchColumn();
    echo "✅ Current questions count: " . $count . "<br>";
    
    if ($count > 0) {
        $stmt = $db->query("SELECT id, assessment_id, question_text, question_type, question_order, points, created_at FROM questions ORDER BY created_at DESC LIMIT 5");
        $recent_questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<h4>Recent Questions:</h4>";
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
        echo "<tr><th>ID</th><th>Assessment ID</th><th>Question Text</th><th>Type</th><th>Order</th><th>Points</th><th>Created</th></tr>";
        foreach ($recent_questions as $q) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($q['id']) . "</td>";
            echo "<td>" . htmlspecialchars($q['assessment_id']) . "</td>";
            echo "<td>" . htmlspecialchars(substr($q['question_text'], 0, 50)) . "...</td>";
            echo "<td>" . htmlspecialchars($q['question_type']) . "</td>";
            echo "<td>" . htmlspecialchars($q['question_order']) . "</td>";
            echo "<td>" . htmlspecialchars($q['points']) . "</td>";
            echo "<td>" . htmlspecialchars($q['created_at']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Test insert capability
    echo "<h3>5. Insert Test</h3>";
    $test_assessment_id = 'test_assess_' . uniqid();
    $test_question_text = 'Test question for diagnostic purposes';
    $test_question_type = 'multiple_choice';
    $test_points = 1;
    $test_order = 1;
    $test_options = json_encode([
        [
            'text' => 'Test Option 1',
            'is_correct' => true,
            'order' => 1
        ],
        [
            'text' => 'Test Option 2',
            'is_correct' => false,
            'order' => 2
        ]
    ]);
    
    $stmt = $db->prepare("
        INSERT INTO questions 
        (assessment_id, question_text, question_type, question_order, points, options) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    if ($stmt->execute([
        $test_assessment_id,
        $test_question_text,
        $test_question_type,
        $test_order,
        $test_points,
        $test_options
    ])) {
        $inserted_id = $db->lastInsertId();
        echo "✅ Test question inserted successfully! ID: " . $inserted_id . "<br>";
        
        // Clean up test data
        $stmt = $db->prepare("DELETE FROM questions WHERE id = ?");
        $stmt->execute([$inserted_id]);
        echo "✅ Test question cleaned up<br>";
    } else {
        $error_info = $stmt->errorInfo();
        echo "❌ Failed to insert test question. Error: " . print_r($error_info, true) . "<br>";
    }
    
    // Check error log
    echo "<h3>6. Error Log Check</h3>";
    $error_log_file = ini_get('error_log');
    if ($error_log_file && file_exists($error_log_file)) {
        echo "✅ Error log file: " . $error_log_file . "<br>";
        $recent_errors = file_get_contents($error_log_file);
        $lines = explode("\n", $recent_errors);
        $recent_lines = array_slice($lines, -20); // Last 20 lines
        echo "<h4>Recent Error Log Entries:</h4>";
        echo "<pre style='background: #f5f5f5; padding: 10px; max-height: 200px; overflow-y: auto;'>";
        echo htmlspecialchars(implode("\n", $recent_lines));
        echo "</pre>";
    } else {
        echo "❌ Error log file not found or not accessible<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
    echo "Stack trace:<br><pre>" . $e->getTraceAsString() . "</pre>";
}
?>
