<?php
/**
 * User ID Generator Utility
 * Generates unique IDs for all user roles in the format NEUST-MGT(ROLE)-00001
 */

/**
 * Generate a unique ID for any role
 * @param PDO $db Database connection
 * @param string $role Role type ('admin', 'teacher', 'student')
 * @return string Generated ID
 */
function generateUserId($db, $role) {
    // Define role prefixes
    $rolePrefixes = [
        'admin' => 'NEUST-MGT(ADM)',
        'teacher' => 'NEUST-MGT(TCH)', 
        'student' => 'NEUST-MGT(STD)'
    ];
    
    if (!isset($rolePrefixes[$role])) {
        throw new Exception("Invalid role: $role");
    }
    
    $prefix = $rolePrefixes[$role];
    
    // Find the highest existing ID for this role
    $stmt = $db->prepare("
        SELECT identifier 
        FROM users 
        WHERE identifier LIKE ? 
        AND role = ?
        ORDER BY identifier DESC 
        LIMIT 1
    ");
    $stmt->execute([$prefix . "-%", $role]);
    $result = $stmt->fetch();
    
    if ($result) {
        // Extract the number from existing ID
        $existingId = $result['identifier'];
        if (preg_match('/' . preg_quote($prefix) . '-(\d+)/', $existingId, $matches)) {
            $nextNumber = intval($matches[1]) + 1;
        } else {
            $nextNumber = 1;
        }
    } else {
        $nextNumber = 1;
    }
    
    // Format the ID with leading zeros (5 digits)
    return sprintf('%s-%05d', $prefix, $nextNumber);
}

/**
 * Generate a unique student ID (backward compatibility)
 * @param PDO $db Database connection
 * @return string Generated student ID
 */
function generateStudentId($db) {
    return generateUserId($db, 'student');
}

/**
 * Generate a unique teacher ID
 * @param PDO $db Database connection
 * @return string Generated teacher ID
 */
function generateTeacherId($db) {
    return generateUserId($db, 'teacher');
}

/**
 * Generate a unique admin ID
 * @param PDO $db Database connection
 * @return string Generated admin ID
 */
function generateAdminId($db) {
    return generateUserId($db, 'admin');
}

/**
 * Check if a user ID already exists
 * @param PDO $db Database connection
 * @param string $userId User ID to check
 * @param string $role Role type
 * @return bool True if exists, false otherwise
 */
function userIdExists($db, $userId, $role) {
    $stmt = $db->prepare("SELECT id FROM users WHERE identifier = ? AND role = ?");
    $stmt->execute([$userId, $role]);
    return $stmt->fetch() !== false;
}

/**
 * Check if a student ID already exists (backward compatibility)
 * @param PDO $db Database connection
 * @param string $studentId Student ID to check
 * @return bool True if exists, false otherwise
 */
function studentIdExists($db, $studentId) {
    return userIdExists($db, $studentId, 'student');
}

/**
 * Get user ID for a user
 * @param PDO $db Database connection
 * @param int $userId User ID
 * @param string $role Role type
 * @return string|null User ID or null if not found
 */
function getUserId($db, $userId, $role) {
    $stmt = $db->prepare("SELECT identifier FROM users WHERE id = ? AND role = ?");
    $stmt->execute([$userId, $role]);
    $result = $stmt->fetch();
    return $result ? $result['identifier'] : null;
}

/**
 * Get student ID for a user (backward compatibility)
 * @param PDO $db Database connection
 * @param int $userId User ID
 * @return string|null Student ID or null if not found
 */
function getStudentId($db, $userId) {
    return getUserId($db, $userId, 'student');
}

/**
 * Assign user ID to a user if they don't have one
 * @param PDO $db Database connection
 * @param int $userId User ID
 * @param string $role Role type
 * @return string|null Generated user ID or null if failed
 */
function assignUserId($db, $userId, $role) {
    // Check if user already has an ID
    $existingId = getUserId($db, $userId, $role);
    if ($existingId) {
        return $existingId;
    }
    
    // Generate new user ID
    $newId = generateUserId($db, $role);
    
    // Update the user record
    $stmt = $db->prepare("UPDATE users SET identifier = ? WHERE id = ? AND role = ?");
    if ($stmt->execute([$newId, $userId, $role])) {
        return $newId;
    }
    
    return null;
}

/**
 * Assign student ID to a user if they don't have one (backward compatibility)
 * @param PDO $db Database connection
 * @param int $userId User ID
 * @return string|null Generated student ID or null if failed
 */
function assignStudentId($db, $userId) {
    return assignUserId($db, $userId, 'student');
}


?>
