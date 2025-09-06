<?php
/**
 * Migration script to convert JSON questions to separate table
 * Run this script to migrate existing JSON questions to the new merged table structure
 */

require_once __DIR__ . '/../config/config.php';

try {
    echo "Starting migration of JSON questions to separate table...\n";
    
    // Get all assessments that have JSON questions
    $stmt = $db->prepare("
        SELECT a.id as assessment_id, a.questions 
        FROM assessments a 
        WHERE a.questions IS NOT NULL AND a.questions != '' AND a.questions != '[]'
    ");
    $stmt->execute();
    $assessments = $stmt->fetchAll();
    
    $total_migrated = 0;
    $total_errors = 0;
    
    foreach ($assessments as $assessment) {
        $assessment_id = $assessment['assessment_id'];
        $questions_json = $assessment['questions'];
        
        echo "Processing assessment ID: $assessment_id\n";
        
        // Decode JSON questions
        $questions = json_decode($questions_json, true);
        
        if (!is_array($questions)) {
            echo "  ❌ Invalid JSON for assessment $assessment_id\n";
            $total_errors++;
            continue;
        }
        
        if (empty($questions)) {
            echo "  ⚠️  No questions found for assessment $assessment_id\n";
            continue;
        }
        
        echo "  📝 Found " . count($questions) . " questions\n";
        
        // Insert each question into the new table
        foreach ($questions as $question) {
            try {
                $question_text = $question['question_text'] ?? '';
                $question_type = $question['question_type'] ?? 'multiple_choice';
                $question_order = $question['question_order'] ?? 1;
                $points = $question['points'] ?? 1;
                $options = $question['options'] ?? [];
                
                if (empty($question_text)) {
                    echo "    ❌ Skipping question with empty text\n";
                    continue;
                }
                
                // Insert question into new table
                $stmt = $db->prepare("
                    INSERT INTO questions 
                    (assessment_id, question_text, question_type, question_order, points, options) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                
                $stmt->execute([
                    $assessment_id,
                    $question_text,
                    $question_type,
                    $question_order,
                    $points,
                    json_encode($options)
                ]);
                
                echo "    ✅ Migrated question: " . substr($question_text, 0, 50) . "...\n";
                $total_migrated++;
                
            } catch (Exception $e) {
                echo "    ❌ Error migrating question: " . $e->getMessage() . "\n";
                $total_errors++;
            }
        }
        
        // Clear the JSON questions column after successful migration
        $stmt = $db->prepare("UPDATE assessments SET questions = NULL WHERE id = ?");
        $stmt->execute([$assessment_id]);
        echo "  🧹 Cleared JSON questions for assessment $assessment_id\n";
    }
    
    echo "\n=== Migration Summary ===\n";
    echo "✅ Total questions migrated: $total_migrated\n";
    echo "❌ Total errors: $total_errors\n";
    echo "📊 Total assessments processed: " . count($assessments) . "\n";
    
    if ($total_errors === 0) {
        echo "\n🎉 Migration completed successfully!\n";
        echo "You can now use the new question management system.\n";
    } else {
        echo "\n⚠️  Migration completed with some errors.\n";
        echo "Please review the errors above and fix them manually if needed.\n";
    }
    
} catch (Exception $e) {
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>

