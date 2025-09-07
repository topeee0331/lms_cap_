<?php
/**
 * Assessment Order Manager
 * 
 * This file contains functions to manage assessment ordering within courses/modules
 * and ensure proper sequential ordering without duplicates or gaps.
 */

/**
 * Get the next available assessment order for a course
 * 
 * @param PDO $pdo Database connection
 * @param int $course_id Course ID
 * @return int Next available order number
 */
function getNextAssessmentOrder($pdo, $course_id) {
    $stmt = $pdo->prepare('SELECT MAX(assessment_order) FROM assessments WHERE course_id = ?');
    $stmt->execute([$course_id]);
    $max_order = $stmt->fetchColumn();
    
    return $max_order ? $max_order + 1 : 1;
}

/**
 * Validate assessment order uniqueness within a course
 * 
 * @param PDO $pdo Database connection
 * @param int $course_id Course ID
 * @param int $order Order number to validate
 * @param string $exclude_id Assessment ID to exclude from validation (for updates)
 * @return bool True if order is unique, false if duplicate
 */
function validateAssessmentOrder($pdo, $course_id, $order, $exclude_id = null) {
    $sql = 'SELECT COUNT(*) FROM assessments WHERE course_id = ? AND assessment_order = ?';
    $params = [$course_id, $order];
    
    if ($exclude_id) {
        $sql .= ' AND id != ?';
        $params[] = $exclude_id;
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    return $stmt->fetchColumn() == 0;
}

/**
 * Rebalance assessment orders for a course to ensure sequential ordering
 * 
 * @param PDO $pdo Database connection
 * @param int $course_id Course ID
 * @return bool True if successful, false on error
 */
function rebalanceAssessmentOrders($pdo, $course_id) {
    try {
        $pdo->beginTransaction();
        
        // Get all assessments for the course ordered by current order, then by creation date
        $stmt = $pdo->prepare('
            SELECT id, assessment_order, created_at 
            FROM assessments 
            WHERE course_id = ? 
            ORDER BY assessment_order ASC, created_at ASC
        ');
        $stmt->execute([$course_id]);
        $assessments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($assessments)) {
            $pdo->rollback();
            return true; // No assessments to rebalance
        }
        
        // Assign sequential order numbers starting from 1
        $new_order = 1;
        foreach ($assessments as $assessment) {
            $stmt = $pdo->prepare('UPDATE assessments SET assessment_order = ? WHERE id = ?');
            $stmt->execute([$new_order, $assessment['id']]);
            $new_order++;
        }
        
        $pdo->commit();
        return true;
        
    } catch (Exception $e) {
        $pdo->rollback();
        error_log("Error rebalancing assessment orders for course $course_id: " . $e->getMessage());
        return false;
    }
}

/**
 * Update assessment order with automatic rebalancing
 * 
 * @param PDO $pdo Database connection
 * @param string $assessment_id Assessment ID
 * @param int $new_order New order number
 * @return array Result array with success status and message
 */
function updateAssessmentOrder($pdo, $assessment_id, $new_order) {
    try {
        // Get assessment details
        $stmt = $pdo->prepare('SELECT course_id, assessment_title FROM assessments WHERE id = ?');
        $stmt->execute([$assessment_id]);
        $assessment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$assessment) {
            return ['success' => false, 'message' => 'Assessment not found.'];
        }
        
        $course_id = $assessment['course_id'];
        
        // Validate order
        if ($new_order < 1) {
            return ['success' => false, 'message' => 'Order must be at least 1.'];
        }
        
        // Check if order is already taken
        if (!validateAssessmentOrder($pdo, $course_id, $new_order, $assessment_id)) {
            // Order is taken, need to rebalance
            if (!rebalanceAssessmentOrders($pdo, $course_id)) {
                return ['success' => false, 'message' => 'Failed to rebalance assessment orders.'];
            }
            
            // After rebalancing, find the correct order for this assessment
            $stmt = $pdo->prepare('
                SELECT assessment_order 
                FROM assessments 
                WHERE course_id = ? 
                ORDER BY assessment_order ASC, created_at ASC
            ');
            $stmt->execute([$course_id]);
            $orders = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            $target_index = $new_order - 1;
            if ($target_index >= count($orders)) {
                $new_order = count($orders);
            } else {
                $new_order = $orders[$target_index];
            }
        }
        
        // Update the assessment order
        $stmt = $pdo->prepare('UPDATE assessments SET assessment_order = ? WHERE id = ?');
        $stmt->execute([$new_order, $assessment_id]);
        
        // Rebalance to ensure sequential ordering
        rebalanceAssessmentOrders($pdo, $course_id);
        
        return [
            'success' => true, 
            'message' => 'Assessment order updated successfully.',
            'new_order' => $new_order
        ];
        
    } catch (Exception $e) {
        error_log("Error updating assessment order: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to update assessment order.'];
    }
}

/**
 * Get assessment order statistics for a course
 * 
 * @param PDO $pdo Database connection
 * @param int $course_id Course ID
 * @return array Statistics array
 */
function getAssessmentOrderStats($pdo, $course_id) {
    $stmt = $pdo->prepare('
        SELECT 
            COUNT(*) as total_assessments,
            MIN(assessment_order) as min_order,
            MAX(assessment_order) as max_order,
            COUNT(DISTINCT assessment_order) as unique_orders
        FROM assessments 
        WHERE course_id = ?
    ');
    $stmt->execute([$course_id]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $stats['has_duplicates'] = $stats['total_assessments'] != $stats['unique_orders'];
    $stats['has_gaps'] = ($stats['max_order'] - $stats['min_order'] + 1) != $stats['unique_orders'];
    $stats['duplicate_orders'] = $stats['total_assessments'] - $stats['unique_orders'];
    $stats['gaps_in_sequence'] = ($stats['max_order'] - $stats['min_order'] + 1) - $stats['unique_orders'];
    
    return $stats;
}

/**
 * Validate that all assessment orders are unique within a course
 * 
 * @param PDO $pdo Database connection
 * @param int $course_id Course ID
 * @return bool True if all orders are unique, false if duplicates exist
 */
function validateAssessmentOrderUniqueness($pdo, $course_id) {
    $stmt = $pdo->prepare('
        SELECT COUNT(*) as total, COUNT(DISTINCT assessment_order) as unique_orders
        FROM assessments 
        WHERE course_id = ?
    ');
    $stmt->execute([$course_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $result['total'] == $result['unique_orders'];
}

/**
 * Auto-assign order to new assessment
 * 
 * @param PDO $pdo Database connection
 * @param int $course_id Course ID
 * @return int Assigned order number
 */
function autoAssignAssessmentOrder($pdo, $course_id) {
    return getNextAssessmentOrder($pdo, $course_id);
}

/**
 * Move assessment to a specific position and rebalance others
 * 
 * @param PDO $pdo Database connection
 * @param string $assessment_id Assessment ID to move
 * @param int $new_position New position (1-based)
 * @return array Result array
 */
function moveAssessmentToPosition($pdo, $assessment_id, $new_position) {
    try {
        // Get assessment details
        $stmt = $pdo->prepare('SELECT course_id FROM assessments WHERE id = ?');
        $stmt->execute([$assessment_id]);
        $assessment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$assessment) {
            return ['success' => false, 'message' => 'Assessment not found.'];
        }
        
        $course_id = $assessment['course_id'];
        
        // Get all assessments for the course
        $stmt = $pdo->prepare('
            SELECT id, assessment_order 
            FROM assessments 
            WHERE course_id = ? 
            ORDER BY assessment_order ASC, created_at ASC
        ');
        $stmt->execute([$course_id]);
        $assessments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($assessments)) {
            return ['success' => false, 'message' => 'No assessments found in course.'];
        }
        
        // Find the assessment to move
        $target_assessment = null;
        $target_index = -1;
        foreach ($assessments as $index => $assess) {
            if ($assess['id'] === $assessment_id) {
                $target_assessment = $assess;
                $target_index = $index;
                break;
            }
        }
        
        if ($target_index === -1) {
            return ['success' => false, 'message' => 'Assessment not found in course.'];
        }
        
        // Validate new position
        $new_position = max(1, min($new_position, count($assessments)));
        $new_index = $new_position - 1;
        
        // If already in the correct position, no need to move
        if ($target_index === $new_index) {
            return ['success' => true, 'message' => 'Assessment is already in the correct position.'];
        }
        
        // Remove target assessment from array
        $moved_assessment = array_splice($assessments, $target_index, 1)[0];
        
        // Insert at new position
        array_splice($assessments, $new_index, 0, [$moved_assessment]);
        
        // Update all assessment orders
        $pdo->beginTransaction();
        
        foreach ($assessments as $index => $assess) {
            $new_order = $index + 1;
            $stmt = $pdo->prepare('UPDATE assessments SET assessment_order = ? WHERE id = ?');
            $stmt->execute([$new_order, $assess['id']]);
        }
        
        $pdo->commit();
        
        return [
            'success' => true, 
            'message' => 'Assessment moved successfully.',
            'new_position' => $new_position
        ];
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollback();
        }
        error_log("Error moving assessment: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to move assessment.'];
    }
}
?>
