<?php
require_once 'config.php';

class AdminFunctions {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    // Admin login
    public function adminLogin($username, $password) {
        $stmt = $this->conn->prepare("SELECT admin_id, username, password FROM admin WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 1) {
            $admin = $result->fetch_assoc();
            if (password_verify($password, $admin['password'])) {
                $_SESSION['admin_id'] = $admin['admin_id'];
                $_SESSION['username'] = $admin['username'];
                $_SESSION['role'] = 'admin';
                return true;
            }
        }
        return false;
    }

    // Add new student
    public function addStudent($roll_number, $first_name, $last_name, $email, $program, $semester) {
        $default_password = password_hash($roll_number, PASSWORD_DEFAULT);
        
        $stmt = $this->conn->prepare("INSERT INTO student (roll_number, first_name, last_name, email, program, semester, password) 
                                     VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssis", $roll_number, $first_name, $last_name, $email, $program, $semester, $default_password);
        return $stmt->execute();
    }

    // Add new faculty
    public function addFaculty($biometric_id, $first_name, $last_name, $email, $department) {
        $default_password = password_hash($biometric_id, PASSWORD_DEFAULT);
        
        $stmt = $this->conn->prepare("INSERT INTO faculty (biometric_id, first_name, last_name, email, department, password) 
                                     VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssss", $biometric_id, $first_name, $last_name, $email, $department, $default_password);
        return $stmt->execute();
    }

    // Add new course
    public function addCourse($course_code, $course_name, $department, $semester, $credits) {
        $stmt = $this->conn->prepare("INSERT INTO course (course_code, course_name, department, semester, credits) 
                                     VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssii", $course_code, $course_name, $department, $semester, $credits);
        return $stmt->execute();
    }

    // Allocate course to faculty
    public function allocateCourse($faculty_id, $course_id, $semester, $academic_year) {
        $stmt = $this->conn->prepare("INSERT INTO faculty_course (faculty_id, course_id, semester, academic_year) 
                                     VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiis", $faculty_id, $course_id, $semester, $academic_year);
        return $stmt->execute();
    }

    // Get all students
    public function getAllStudents() {
        $result = $this->conn->query("SELECT * FROM student ORDER BY roll_number");
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    // Get all faculty
    public function getAllFaculty() {
        $result = $this->conn->query("SELECT * FROM faculty ORDER BY last_name, first_name");
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    // Get all courses
    public function getAllCourses() {
        $result = $this->conn->query("SELECT * FROM course ORDER BY course_code");
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    // Remove student
    public function removeStudent($student_id) {
        $stmt = $this->conn->prepare("DELETE FROM student WHERE student_id = ?");
        $stmt->bind_param("i", $student_id);
        return $stmt->execute();
    }

    // Remove faculty
    public function removeFaculty($faculty_id) {
        $stmt = $this->conn->prepare("DELETE FROM faculty WHERE faculty_id = ?");
        $stmt->bind_param("i", $faculty_id);
        return $stmt->execute();
    }

    // Enable/disable feedback portal
    public function setPortalStatus($status) {
        // This would typically update a setting in a settings table
        // For simplicity, we'll assume this toggles a session variable
        $_SESSION['portal_active'] = $status;
        return true;
    }

    // Generate faculty performance report
    public function generateFacultyReport($faculty_id, $semester, $academic_year) {
        $report = [];
        
        // Get faculty info
        $stmt = $this->conn->prepare("SELECT * FROM faculty WHERE faculty_id = ?");
        $stmt->bind_param("i", $faculty_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $report['faculty_info'] = $result->fetch_assoc();
        
        // Get courses taught
        $stmt = $this->conn->prepare("SELECT c.* FROM faculty_course fc 
                                     JOIN course c ON fc.course_id = c.course_id 
                                     WHERE fc.faculty_id = ? AND fc.semester = ? AND fc.academic_year = ?");
        $stmt->bind_param("iis", $faculty_id, $semester, $academic_year);
        $stmt->execute();
        $result = $stmt->get_result();
        $report['courses'] = $result->fetch_all(MYSQLI_ASSOC);
        
        // Get feedback summary for each course
        foreach ($report['courses'] as &$course) {
            $stmt = $this->conn->prepare("SELECT q.question_text, AVG(r.rating) as avg_rating 
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
            
            // Count responses
            $stmt = $this->conn->prepare("SELECT COUNT(*) as response_count FROM feedback_responses 
                                         WHERE faculty_id = ? AND course_id = ? 
                                         AND semester = ? AND academic_year = ?");
            $stmt->bind_param("iiis", $faculty_id, $course['course_id'], $semester, $academic_year);
            $stmt->execute();
            $result = $stmt->get_result();
            $course['response_count'] = $result->fetch_assoc()['response_count'];
        }
        
        return $report;
    }
}
?>