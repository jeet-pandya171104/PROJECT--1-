<?php
require_once 'config.php';

class StudentFunctions {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    // Student login
    public function studentLogin($roll_number, $password) {
        $stmt = $this->conn->prepare("SELECT student_id, roll_number, password FROM student WHERE roll_number = ?");
        $stmt->bind_param("s", $roll_number);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 1) {
            $student = $result->fetch_assoc();
            if (password_verify($password, $student['password'])) {
                $_SESSION['student_id'] = $student['student_id'];
                $_SESSION['roll_number'] = $student['roll_number'];
                $_SESSION['role'] = 'student';
                return true;
            }
        }
        return false;
    }

    // Change password
    public function changePassword($student_id, $current_password, $new_password) {
        // Verify current password
        $stmt = $this->conn->prepare("SELECT password FROM student WHERE student_id = ?");
        $stmt->bind_param("i", $student_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 1) {
            $student = $result->fetch_assoc();
            if (password_verify($current_password, $student['password'])) {
                // Update to new password
                $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $this->conn->prepare("UPDATE student SET password = ? WHERE student_id = ?");
                $stmt->bind_param("si", $new_hash, $student_id);
                return $stmt->execute();
            }
        }
        return false;
    }

    // Get courses for feedback (courses the student is enrolled in for current semester)
    public function getCoursesForFeedback($student_id, $semester, $academic_year) {
        // In a real system, you'd have an enrollment table. For simplicity, we'll assume all courses in the student's semester
        $stmt = $this->conn->prepare("SELECT c.course_id, c.course_code, c.course_name, f.faculty_id, 
                                     f.first_name as faculty_first_name, f.last_name as faculty_last_name
                                     FROM faculty_course fc
                                     JOIN course c ON fc.course_id = c.course_id
                                     JOIN faculty f ON fc.faculty_id = f.faculty_id
                                     WHERE c.semester = (SELECT semester FROM student WHERE student_id = ?)
                                     AND fc.semester = ? AND fc.academic_year = ?
                                     AND NOT EXISTS (
                                         SELECT 1 FROM feedback_responses fr 
                                         WHERE fr.student_id = ? AND fr.course_id = c.course_id
                                         AND fr.semester = ? AND fr.academic_year = ?
                                     )");
        $stmt->bind_param("iisiis", $student_id, $semester, $academic_year, $student_id, $semester, $academic_year);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    // Get feedback questions
    public function getFeedbackQuestions() {
        $result = $this->conn->query("SELECT * FROM feedback_questions WHERE is_active = TRUE");
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    // Submit feedback
    public function submitFeedback($student_id, $faculty_id, $course_id, $semester, $academic_year, $ratings) {
        $this->conn->begin_transaction();
        
        try {
            // Insert response header
            $stmt = $this->conn->prepare("INSERT INTO feedback_responses 
                                         (student_id, faculty_id, course_id, semester, academic_year) 
                                         VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("iiiis", $student_id, $faculty_id, $course_id, $semester, $academic_year);
            $stmt->execute();
            $response_id = $this->conn->insert_id;
            
            // Insert each rating
            $stmt = $this->conn->prepare("INSERT INTO feedback_ratings (response_id, question_id, rating) 
                                         VALUES (?, ?, ?)");
            foreach ($ratings as $question_id => $rating) {
                $stmt->bind_param("iii", $response_id, $question_id, $rating);
                $stmt->execute();
            }
            
            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollback();
            return false;
        }
    }
}
?>