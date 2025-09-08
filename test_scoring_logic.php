<?php
/**
 * Test script to verify scoring logic for all question types
 * This script tests the answer validation logic without requiring a full assessment
 */

// Test data - simulate different question types and answers
$test_cases = [
    // Test Case 1: Multiple Choice - Single Correct Answer
    [
        'question_type' => 'multiple_choice',
        'options' => [
            ['text' => 'Option A', 'is_correct' => false, 'order' => 1],
            ['text' => 'Option B', 'is_correct' => true, 'order' => 2],
            ['text' => 'Option C', 'is_correct' => false, 'order' => 3],
            ['text' => 'Option D', 'is_correct' => false, 'order' => 4]
        ],
        'student_answer' => '2',
        'expected_correct' => true,
        'description' => 'Multiple Choice - Single Correct Answer (Option B)'
    ],
    
    // Test Case 2: Multiple Choice - Multiple Correct Answers
    [
        'question_type' => 'multiple_choice',
        'options' => [
            ['text' => 'Option A', 'is_correct' => true, 'order' => 1],
            ['text' => 'Option B', 'is_correct' => false, 'order' => 2],
            ['text' => 'Option C', 'is_correct' => true, 'order' => 3],
            ['text' => 'Option D', 'is_correct' => false, 'order' => 4]
        ],
        'student_answer' => '1,3',
        'expected_correct' => true,
        'description' => 'Multiple Choice - Multiple Correct Answers (A and C)'
    ],
    
    // Test Case 3: Multiple Choice - Wrong Answer
    [
        'question_type' => 'multiple_choice',
        'options' => [
            ['text' => 'Option A', 'is_correct' => true, 'order' => 1],
            ['text' => 'Option B', 'is_correct' => false, 'order' => 2],
            ['text' => 'Option C', 'is_correct' => false, 'order' => 3],
            ['text' => 'Option D', 'is_correct' => false, 'order' => 4]
        ],
        'student_answer' => '2',
        'expected_correct' => false,
        'description' => 'Multiple Choice - Wrong Answer (B instead of A)'
    ],
    
    // Test Case 4: Multiple Choice - Partial Answer (Missing one correct)
    [
        'question_type' => 'multiple_choice',
        'options' => [
            ['text' => 'Option A', 'is_correct' => true, 'order' => 1],
            ['text' => 'Option B', 'is_correct' => true, 'order' => 2],
            ['text' => 'Option C', 'is_correct' => false, 'order' => 3],
            ['text' => 'Option D', 'is_correct' => false, 'order' => 4]
        ],
        'student_answer' => '1',
        'expected_correct' => false,
        'description' => 'Multiple Choice - Partial Answer (Only A, missing B)'
    ],
    
    // Test Case 5: True/False - Correct Answer
    [
        'question_type' => 'true_false',
        'options' => [
            ['text' => 'True', 'is_correct' => true, 'order' => 1],
            ['text' => 'False', 'is_correct' => false, 'order' => 2]
        ],
        'student_answer' => 'True',
        'expected_correct' => true,
        'description' => 'True/False - Correct Answer (True)'
    ],
    
    // Test Case 6: True/False - Wrong Answer
    [
        'question_type' => 'true_false',
        'options' => [
            ['text' => 'True', 'is_correct' => true, 'order' => 1],
            ['text' => 'False', 'is_correct' => false, 'order' => 2]
        ],
        'student_answer' => 'False',
        'expected_correct' => false,
        'description' => 'True/False - Wrong Answer (False)'
    ],
    
    // Test Case 7: Identification - Correct Answer
    [
        'question_type' => 'identification',
        'options' => [
            ['text' => 'HyperText Markup Language', 'is_correct' => true, 'order' => 1]
        ],
        'student_answer' => 'HyperText Markup Language',
        'expected_correct' => true,
        'description' => 'Identification - Correct Answer (Exact match)'
    ],
    
    // Test Case 8: Identification - Case Insensitive
    [
        'question_type' => 'identification',
        'options' => [
            ['text' => 'HyperText Markup Language', 'is_correct' => true, 'order' => 1]
        ],
        'student_answer' => 'hypertext markup language',
        'expected_correct' => true,
        'description' => 'Identification - Case Insensitive Match'
    ],
    
    // Test Case 9: Identification - Wrong Answer
    [
        'question_type' => 'identification',
        'options' => [
            ['text' => 'HyperText Markup Language', 'is_correct' => true, 'order' => 1]
        ],
        'student_answer' => 'HTML',
        'expected_correct' => false,
        'description' => 'Identification - Wrong Answer (HTML instead of full name)'
    ]
];

// Function to test scoring logic (copied from assessment.php)
function testScoringLogic($question_type, $options, $student_answer) {
    if ($question_type === 'identification') {
        $correct_answer = '';
        if ($options && is_array($options)) {
            foreach ($options as $option) {
                if (isset($option['is_correct']) && $option['is_correct']) {
                    $correct_answer = $option['text'] ?? '';
                    break;
                }
            }
        }
        return strtoupper(trim($student_answer)) == strtoupper(trim($correct_answer));
        
    } elseif ($question_type === 'true_false') {
        $correct_answer = '';
        if ($options && is_array($options)) {
            foreach ($options as $option) {
                if (isset($option['is_correct']) && $option['is_correct']) {
                    $correct_answer = $option['text'] ?? '';
                    break;
                }
            }
        }
        return strtoupper(trim($student_answer)) == strtoupper(trim($correct_answer));
        
    } else {
        // Multiple choice
        $correct_option_orders = [];
        if ($options && is_array($options)) {
            foreach ($options as $option) {
                if (isset($option['is_correct']) && $option['is_correct']) {
                    $correct_option_orders[] = (int)$option['order'];
                }
            }
        }
        
        if (!empty($student_answer)) {
            $student_answers = strpos($student_answer, ',') !== false ? 
                explode(',', $student_answer) : [$student_answer];
            $student_answers = array_map('intval', $student_answers);
            sort($student_answers);
            sort($correct_option_orders);
            return $student_answers === $correct_option_orders;
        }
        return false;
    }
}

// Run tests
echo "<h2>Scoring Logic Test Results</h2>\n";
echo "<table border='1' cellpadding='10' cellspacing='0'>\n";
echo "<tr><th>Test Case</th><th>Question Type</th><th>Student Answer</th><th>Expected</th><th>Actual</th><th>Status</th></tr>\n";

$passed = 0;
$total = count($test_cases);

foreach ($test_cases as $index => $test_case) {
    $actual_result = testScoringLogic(
        $test_case['question_type'],
        $test_case['options'],
        $test_case['student_answer']
    );
    
    $status = $actual_result === $test_case['expected_correct'] ? 'PASS' : 'FAIL';
    $status_color = $status === 'PASS' ? 'green' : 'red';
    
    if ($status === 'PASS') {
        $passed++;
    }
    
    echo "<tr>";
    echo "<td>" . ($index + 1) . "</td>";
    echo "<td>" . $test_case['question_type'] . "</td>";
    echo "<td>" . htmlspecialchars($test_case['student_answer']) . "</td>";
    echo "<td>" . ($test_case['expected_correct'] ? 'Correct' : 'Incorrect') . "</td>";
    echo "<td>" . ($actual_result ? 'Correct' : 'Incorrect') . "</td>";
    echo "<td style='color: $status_color; font-weight: bold;'>$status</td>";
    echo "</tr>\n";
}

echo "</table>\n";
echo "<h3>Summary: $passed/$total tests passed</h3>\n";

if ($passed === $total) {
    echo "<p style='color: green; font-weight: bold;'>✅ All tests passed! Scoring logic is working correctly.</p>\n";
} else {
    echo "<p style='color: red; font-weight: bold;'>❌ Some tests failed. Please check the scoring logic.</p>\n";
}
?>
