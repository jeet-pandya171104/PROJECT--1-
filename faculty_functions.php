<?php
require_once 'config.php';

class FacultyFunctions {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    // Faculty login
    public function facultyLogin($biometric_id, $password) {
        $stmt = $this->conn->prepare("SELECT faculty_id, biometric_id, password FROM faculty WHERE biometric_id = ?");
        $stmt->bind_param("s", $biometric_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 1) {
            $faculty = $result->fetch_assoc();
            if (password_verify($password, $faculty['password'])) {
                $_SESSION['faculty_id'] = $faculty['faculty_id'];
                $_SESSION['biometric_id'] = $faculty['biometric_id'];
                $_SESSION['role'] = 'faculty';
                return true;
            }
        }
        return false;
    }

    // Change password
    public function changePassword($faculty_id, $current_password, $new_password) {
        // Verify current password
        $stmt = $this->conn->prepare("SELECT password FROM faculty WHERE faculty_id = ?");
        $stmt->bind_param("i", $faculty_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 1) {
            $faculty = $result->fetch_assoc();
            if (password_verify($current_password, $faculty['password'])) {
                // Update to new password
                $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $this->conn->prepare("UPDATE faculty SET password = ? WHERE faculty_id = ?");
                $stmt->bind_param("si", $new_hash, $faculty_id);
                return $stmt->execute();
            }
        }
        return false;
    }

    // Get faculty courses for a semester
    public function getFacultyCourses($faculty_id, $semester, $academic_year) {
        $stmt = $this->conn->prepare("SELECT c.course_id, c.course_code, c.course_name 
                                    FROM faculty_course fc
                                    JOIN course c ON fc.course_id = c.course_id
                                    WHERE fc.faculty_id = ? AND fc.semester = ? AND fc.academic_year = ?");
        $stmt->bind_param("iis", $faculty_id, $semester, $academic_year);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    // Get feedback reports for faculty
    public function getFeedbackReports($faculty_id, $semester, $academic_year) {
        $reports = [];
        
        // Get courses taught in this semester
        $courses = $this->getFacultyCourses($faculty_id, $semester, $academic_year);
        
        foreach ($courses as $course) {
            // Get average ratings for each question
            $stmt = $this->conn->prepare("SELECT q.question_text, AVG(r.rating) as avg_rating, COUNT(r.rating) as response_count
                                         FROM feedback_responses fr
                                         JOIN feedback_ratings r ON fr.response_id = r.response_id
                                         JOIN feedback_questions q ON r.question_id = q.question_id
                                         WHERE fr.faculty_id = ? AND fr.course_id = ?
                                         AND fr.semester = ? AND fr.academic_year = ?
                                         GROUP BY q.question_id");
            $stmt->bind_param("iiis", $faculty_id, $course['course_id'], $semester, $academic_year);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $course['feedback'] = $result->fetch_all(MYSQLI_ASSOC);
            $reports[] = $course;
        }
        
        return $reports;
    }

    // Get detailed feedback comments (if implemented)
    public function getFeedbackComments($faculty_id, $course_id, $semester, $academic_year) {
        $stmt = $this->conn->prepare("SELECT fr.submission_date, s.roll_number, 
                                     GROUP_CONCAT(CONCAT(q.question_text, ': ', r.rating) as ratings
                                     FROM feedback_responses fr
                                     JOIN feedback_ratings r ON fr.response_id = r.response_id
                                     JOIN feedback_questions q ON r.question_id = q.question_id
                                     JOIN student s ON fr.student_id = s.student_id
                                     WHERE fr.faculty_id = ? AND fr.course_id = ?
                                     AND fr.semester = ? AND fr.academic_year = ?
                                     GROUP BY fr.response_id, s.roll_number, fr.submission_date
                                     ORDER BY fr.submission_date DESC");
        $stmt->bind_param("iiis", $faculty_id, $course_id, $semester, $academic_year);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }
}
?>