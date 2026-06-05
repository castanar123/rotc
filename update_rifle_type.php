<?php
/**
 * Rifle Type Management Functions
 * Helper functions for managing rifle types
 */

require_once 'includes/db.php';
require_once 'includes/functions.php';

/**
 * Get available rifle types
 * @return array Available rifle types
 */
function getRifleTypes() {
    return ['mechanical rifle', 'wooden rifle'];
}

/**
 * Update rifle type for a specific rifle
 * @param int $rifle_id The rifle ID
 * @param string $rifle_type The new rifle type
 * @return array Result with success status and message
 */
function updateRifleType($rifle_id, $rifle_type) {
    global $link;
    
    $valid_types = getRifleTypes();
    if (!in_array($rifle_type, $valid_types)) {
        return ['success' => false, 'message' => 'Invalid rifle type'];
    }
    
    try {
        $stmt = $link->prepare("UPDATE rifles SET rifle_type = ? WHERE id = ?");
        $stmt->bind_param("si", $rifle_type, $rifle_id);
        
        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Rifle type updated successfully'];
        } else {
            return ['success' => false, 'message' => 'Failed to update rifle type'];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
}

/**
 * Get rifles by type
 * @param string $rifle_type The rifle type to filter by
 * @return array Rifles of the specified type
 */
function getRiflesByType($rifle_type) {
    global $link;
    
    try {
        $stmt = $link->prepare("SELECT * FROM rifles WHERE rifle_type = ? ORDER BY rifle_number");
        $stmt->bind_param("s", $rifle_type);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $rifles = [];
        while ($row = $result->fetch_assoc()) {
            $rifles[] = $row;
        }
        
        return $rifles;
    } catch (Exception $e) {
        error_log("Error getting rifles by type: " . $e->getMessage());
        return [];
    }
}

/**
 * Get rifle type statistics
 * @return array Statistics by rifle type
 */
function getRifleTypeStatistics() {
    global $link;
    
    try {
        $stats = [];
        
        $stmt = $link->prepare("SELECT rifle_type, status, COUNT(*) as count FROM rifles GROUP BY rifle_type, status");
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $type = $row['rifle_type'];
            $status = $row['status'];
            $count = $row['count'];
            
            if (!isset($stats[$type])) {
                $stats[$type] = ['total' => 0, 'available' => 0, 'borrowed' => 0, 'maintenance' => 0];
            }
            
            $stats[$type]['total'] += $count;
            $stats[$type][$status] = $count;
        }
        
        return $stats;
    } catch (Exception $e) {
        error_log("Error getting rifle type statistics: " . $e->getMessage());
        return [];
    }
}

/**
 * Auto-detect and set rifle type based on rifle number pattern
 * @param string $rifle_number The rifle number
 * @return string The detected rifle type
 */
function detectRifleType($rifle_number) {
    if (preg_match('/^\d+$/', $rifle_number)) {
        return 'wooden rifle'; // numeric rifle numbers are wooden
    } elseif (preg_match('/^R\d+$/', $rifle_number)) {
        return 'mechanical rifle'; // R-prefixed are mechanical
    } elseif (preg_match('/^TEST/', $rifle_number)) {
        return 'mechanical rifle'; // test rifles are mechanical
    }
    
    return 'mechanical rifle'; // default
}

?>