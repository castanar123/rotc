-- Updated database schema for enhanced attendance system

-- Use the database
USE attendance_system;

-- Add gender and platoon columns to students table if they don't exist
ALTER TABLE students
ADD COLUMN IF NOT EXISTS gender ENUM('male', 'female') NOT NULL DEFAULT 'male',
ADD COLUMN IF NOT EXISTS platoon VARCHAR(20) NOT NULL DEFAULT 'Alpha';

-- Create platoons table for configuration
CREATE TABLE IF NOT EXISTS platoons (
    platoon_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(20) NOT NULL UNIQUE
);

-- Insert default platoons
INSERT IGNORE INTO platoons (name) VALUES
('Alpha'),
('Bravo'),
('Charlie'),
('Delta'),
('Echo');

-- Create sessions table to store scanner sessions
CREATE TABLE IF NOT EXISTS scanner_sessions (
    session_id VARCHAR(64) PRIMARY KEY,
    td INT NOT NULL,
    semester INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_active TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    device_info VARCHAR(255),
    ip_address VARCHAR(45)
);

-- Create attendance_summary view for quick statistics
CREATE OR REPLACE VIEW attendance_summary AS
SELECT 
    a.td,
    a.semester,
    DATE(a.timestamp) as attendance_date,
    s.platoon,
    s.gender,
    COUNT(a.id) as total_present,
    (SELECT COUNT(*) FROM students WHERE platoon = s.platoon AND gender = s.gender) as total_strength,
    (SELECT COUNT(*) FROM students WHERE platoon = s.platoon AND gender = s.gender) - COUNT(a.id) as total_absent
FROM 
    attendance a
JOIN 
    students s ON a.student_id = s.student_id
GROUP BY 
    a.td, a.semester, DATE(a.timestamp), s.platoon, s.gender;

-- Create a function to get attendance statistics
DELIMITER //
CREATE OR REPLACE FUNCTION get_attendance_stats(p_td INT, p_semester INT, p_date DATE) 
RETURNS JSON
DETERMINISTIC
BEGIN
    DECLARE result JSON;
    
    SELECT JSON_OBJECT(
        'date', p_date,
        'td', p_td,
        'semester', p_semester,
        'total', (
            SELECT JSON_OBJECT(
                'strength', COUNT(*),
                'present', COUNT(a.id),
                'absent', COUNT(*) - COUNT(a.id),
                'percentage', ROUND((COUNT(a.id) / COUNT(*)) * 100, 2)
            )
            FROM students s
            LEFT JOIN attendance a ON s.student_id = a.student_id 
                AND a.td = p_td 
                AND a.semester = p_semester 
                AND DATE(a.timestamp) = p_date
        ),
        'by_gender', (
            SELECT JSON_OBJECTAGG(
                gender, JSON_OBJECT(
                    'strength', COUNT(*),
                    'present', COUNT(a.id),
                    'absent', COUNT(*) - COUNT(a.id),
                    'percentage', ROUND((COUNT(a.id) / COUNT(*)) * 100, 2)
                )
            )
            FROM students s
            LEFT JOIN attendance a ON s.student_id = a.student_id 
                AND a.td = p_td 
                AND a.semester = p_semester 
                AND DATE(a.timestamp) = p_date
            GROUP BY gender
        ),
        'by_platoon', (
            SELECT JSON_OBJECTAGG(
                platoon, JSON_OBJECT(
                    'strength', COUNT(*),
                    'present', COUNT(a.id),
                    'absent', COUNT(*) - COUNT(a.id),
                    'percentage', ROUND((COUNT(a.id) / COUNT(*)) * 100, 2)
                )
            )
            FROM students s
            LEFT JOIN attendance a ON s.student_id = a.student_id 
                AND a.td = p_td 
                AND a.semester = p_semester 
                AND DATE(a.timestamp) = p_date
            GROUP BY platoon
        )
    ) INTO result;
    
    RETURN result;
END //
DELIMITER ;

-- Update sample student data with gender and platoon
UPDATE students SET gender = 'male', platoon = 'Alpha' WHERE student_id = '20230001';
UPDATE students SET gender = 'female', platoon = 'Bravo' WHERE student_id = '20230002';
UPDATE students SET gender = 'male', platoon = 'Charlie' WHERE student_id = '20230003';

-- Insert more sample student data
INSERT IGNORE INTO students (student_id, name, gender, platoon) VALUES
('20230004', 'Sarah Johnson', 'female', 'Alpha'),
('20230005', 'Robert Williams', 'male', 'Bravo'),
('20230006', 'Emily Davis', 'female', 'Charlie'),
('20230007', 'James Brown', 'male', 'Delta'),
('20230008', 'Jessica Miller', 'female', 'Delta'),
('20230009', 'David Wilson', 'male', 'Echo'),
('20230010', 'Jennifer Moore', 'female', 'Echo');