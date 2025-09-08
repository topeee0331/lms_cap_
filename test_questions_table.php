<?php
require_once 'config/config.php';

echo "<h2>Testing Questions Table Structure</h2>";

try {
    // Test database connection
    $stmt = $db->query("SELECT 1");
    echo "✅ Database connection successful<br>";
    
    // Check if questions table exists
    $stmt = $db->query("SHOW TABLES LIKE 'questions'");
    if ($stmt->rowCount() > 0) {
        echo "✅ Questions table exists<br>";
        
        // Get table structure
        $stmt = $db->query("DESCRIBE questions");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<h3>Table Structure:</h3>";
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
        foreach ($columns as $column) {
            echo "<tr>";
            echo "<td>" . $column['Field'] . "</td>";
            echo "<td>" . $column['Type'] . "</td>";
            echo "<td>" . $column['Null'] . "</td>";
            echo "<td>" . $column['Key'] . "</td>";
            echo "<td>" . $column['Default'] . "</td>";
            echo "<td>" . $column['Extra'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Test inserting a simple question
        echo "<h3>Testing Question Insert:</h3>";
        
        $test_assessment_id = 'test_assess_' . uniqid();
        $test_question_text = 'Test question for database structure verification';
        $test_question_type = 'multiple_choice';
        $test_points = 1;
        $test_order = 1;
        $test_options = json_encode([
            [
                'text' => 'Option 1',
                'is_correct' => true,
                'order' => 1
            ],
            [
                'text' => 'Option 2',
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
            
            // Verify the insert
            $stmt = $db->prepare("SELECT * FROM questions WHERE id = ?");
            $stmt->execute([$inserted_id]);
            $question = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($question) {
                echo "✅ Question retrieved successfully:<br>";
                echo "<pre>" . print_r($question, true) . "</pre>";
                
                // Clean up test data
                $stmt = $db->prepare("DELETE FROM questions WHERE id = ?");
                $stmt->execute([$inserted_id]);
                echo "✅ Test question cleaned up<br>";
            } else {
                echo "❌ Failed to retrieve inserted question<br>";
            }
        } else {
            $error_info = $stmt->errorInfo();
            echo "❌ Failed to insert test question. Error: " . print_r($error_info, true) . "<br>";
        }
        
    } else {
        echo "❌ Questions table does not exist!<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}
?>
