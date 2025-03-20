<?php
session_start();
require_once 'functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
	header("Location: login.php");
	exit;
}

// Enhanced user status verification
try {
	// Prepare a comprehensive query to check user status
	$stmt = $pdo->prepare("
        SELECT 
            u.id, 
            u.is_active, 
            u.role, 
            u.username,
            sd.graduation_date,
            CASE 
                WHEN u.role = 'student' AND DATE(sd.graduation_date) < DATE_SUB(CURDATE(), INTERVAL 1 DAY) THEN TRUE 
                ELSE FALSE 
            END AS is_student_expired,
            us.session_token IS NOT NULL AS has_valid_session
        FROM users u
        LEFT JOIN student_details sd ON u.id = sd.user_id
        LEFT JOIN user_sessions us ON u.id = us.user_id AND us.session_token = ?
        WHERE u.id = ?
    ");

	// Execute with session token and user ID
	$stmt->execute([
		$_SESSION['session_token'] ?? '',
		$_SESSION['user_id']
	]);
	$user = $stmt->fetch(PDO::FETCH_ASSOC);

	// Determine if logout is necessary
	$shouldLogout = (
		!$user ||
		$user['is_active'] === false ||
		($user['role'] === 'student' && $user['is_student_expired']) ||
		$user['has_valid_session'] == 0
	);

	// Logout process if conditions are met
	if ($shouldLogout) {
		// Invalidate and remove session
		if (isset($_SESSION['user_id']) && isset($_SESSION['session_token'])) {
			$invalidateStmt = $pdo->prepare("DELETE FROM user_sessions WHERE user_id = ? AND session_token = ?");
			$invalidateStmt->execute([$_SESSION['user_id'], $_SESSION['session_token']]);
		}

		// Destroy session
		unset($_SESSION['user_id']);
		unset($_SESSION['role']);
		unset($_SESSION['session_token']);
		session_destroy();

		// Determine logout reason
		$errorMessage = "Account access denied";
		if ($user) {
			if (!$user['is_active']) {
				$errorMessage = "Account is inactive";
			} elseif ($user['role'] === 'student' && $user['is_student_expired']) {
				$errorMessage = "Your student account has expired";
			} elseif ($user['has_valid_session'] == 0) {
				$errorMessage = "Session expired";
			}
		}

		// Redirect with error message
		header("Location: login.php?error=" . urlencode($errorMessage));
		exit;
	}
} catch (PDOException $e) {
	// Log the error and redirect
	error_log("User verification error: " . $e->getMessage());
	header("Location: login.php?error=System error");
	exit;
}
include 'templates/header.php';
$csrf_token = generateCsrfToken();
error_log("Generated CSRF token in index.php: " . $csrf_token);
?>
<!DOCTYPE html>
<html>

<head>
	<title>School Management System</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
	<link rel="stylesheet" href="includes/style.css">
</head>

<body>
	<div class="container mt-4">

		<h1 class="mb-4">School Management System</h1>

		<?php if ($_SESSION['role'] === 'admin'): ?>
			<!-- Admin View: Student Creation Form -->
			<div class="form-section" id="createStudent-section">
				<h2>Create Student</h2>
				<form id="createStudentForm" class="needs-validation" novalidate enctype="multipart/form-data">
					<input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
					<input type="hidden" name="action" value="create_student">
					<div class="mb-3">
						<input type="text" name="username" class="form-control" placeholder="Username" required>
						<div class="invalid-feedback">Please enter a username.</div>
					</div>
					<div class="mb-3">
						<input type="email" name="email" class="form-control" placeholder="Email" required>
						<div class="invalid-feedback">Please enter a valid email.</div>
					</div>
					<div class="mb-3">
						<input type="text" name="full_name" class="form-control" placeholder="Full Name" required>
						<div class="invalid-feedback">Please enter a full name.</div>
					</div>
					<div class="mb-3">
						<input type="text" name="student_id" class="form-control" placeholder="Student ID" required>
						<div class="invalid-feedback">Please enter a student ID.</div>
					</div>
					<div class="mb-3">
						<select name="course_id" class="form-control" required>
							<option value="">Select Course</option>
							<?php
							$stmt = $pdo->query("SELECT id, course_code, course_name FROM courses");
							while ($course = $stmt->fetch(PDO::FETCH_ASSOC)) {
								echo "<option value='{$course['id']}'>" . htmlspecialchars($course['course_code']) . " - " . htmlspecialchars($course['course_name']) . "</option>";
							}
							?>
						</select>
						<div class="invalid-feedback">Please select a course.</div>
					</div>
					<div class="mb-3">
						<input type="date" name="enrollment_date" class="form-control" required>
						<div class="invalid-feedback">Please select an enrollment date.</div>
					</div>
					<div class="mb-3">
						<input type="date" name="graduation_date" class="form-control" required>
						<div class="invalid-feedback">Please select a graduation date.</div>
					</div>
					<div class="mb-3">
						<input type="tel" name="phone" class="form-control" placeholder="Phone Number">
					</div>
					<div class="mb-3">
						<textarea name="address" class="form-control" placeholder="Address"></textarea>
					</div>
					<div class="mb-3">
						<label for="profile_image_student" class="form-label">Profile Image URL</label>
						<input type="url" name="profile_image" id="profile_image_student" class="form-control" placeholder="https://example.com/image.jpg">
						<div class="invalid-feedback">Please enter a valid URL.</div>
					</div>
					<button type="submit" class="btn btn-primary">Create Student</button>
					<button type="button" class="btn btn-secondary clear-btn">Clear</button>
				</form>
			</div>

			<!-- Admin View: Teacher Creation Form -->
			<div class="form-section" id="teacher-section" style="display: none;">
				<h2>Create Teacher</h2>
				<form id="createTeacherForm" class="needs-validation" novalidate enctype="multipart/form-data">
					<input type="hidden" name="action" value="create_teacher">
					<input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
					<div class="mb-3">
						<input type="text" name="username" class="form-control" placeholder="Username" required>
						<div class="invalid-feedback">Please enter a username.</div>
					</div>
					<div class="mb-3">
						<input type="email" name="email" class="form-control" placeholder="Email" required>
						<div class="invalid-feedback">Please enter a valid email.</div>
					</div>
					<div class="mb-3">
						<input type="text" name="full_name" class="form-control" placeholder="Full Name" required>
						<div class="invalid-feedback">Please enter a full name.</div>
					</div>
					<div class="mb-3">
						<select name="course_id" class="form-control" required>
							<option value="">Select Course</option>
							<?php
							$stmt = $pdo->query("SELECT id, course_code, course_name FROM courses");
							while ($course = $stmt->fetch(PDO::FETCH_ASSOC)) {
								echo "<option value='{$course['id']}'>" . htmlspecialchars($course['course_code']) . " - " . htmlspecialchars($course['course_name']) . "</option>";
							}
							?>
						</select>
						<div class="invalid-feedback">Please select a course.</div>
					</div>
					<div class="mb-3">
						<input type="date" name="hire_date" class="form-control" required>
						<div class="invalid-feedback">Please select a hire date.</div>
					</div>
					<div class="mb-3">
						<input type="text" name="qualification" class="form-control" placeholder="Qualification">
					</div>
					<div class="mb-3">
						<input type="tel" name="phone" class="form-control" placeholder="Phone Number">
					</div>
					<div class="mb-3">
						<label for="profile_image_teacher" class="form-label">Profile Image URL</label>
						<input type="url" name="profile_image" id="profile_image_teacher" class="form-control" placeholder="https://example.com/image.jpg">
						<div class="invalid-feedback">Please enter a valid URL.</div>
					</div>
					<button type="submit" class="btn btn-primary">Create Teacher</button>
					<button type="button" class="btn btn-secondary clear-btn">Clear</button>
				</form>
			</div>

			<!-- Admin View: Course Creation Form -->
			<div class="form-section" id="course-section" style="display: none;">
				<h2>Create Course</h2>
				<form id="createCourseForm" class="needs-validation" novalidate>
					<input type="hidden" name="action" value="create_course">
					<input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
					<div class="mb-3">
						<input type="text" name="course_code" class="form-control" placeholder="Course Code" required>
						<div class="invalid-feedback">Please enter a course code.</div>
					</div>
					<div class="mb-3">
						<input type="text" name="course_name" class="form-control" placeholder="Course Name" required>
						<div class="invalid-feedback">Please enter a course name.</div>
					</div>
					<div class="mb-3">
						<input type="text" name="department" class="form-control" placeholder="Department" required>
						<div class="invalid-feedback">Please enter a department.</div>
					</div>
					<div class="mb-3">
						<input type="number" name="credits" class="form-control" placeholder="Credits" required>
						<div class="invalid-feedback">Please enter the number of credits.</div>
					</div>
					<div class="mb-3">
						<textarea name="description" class="form-control" placeholder="Description"></textarea>
					</div>
					<button type="submit" class="btn btn-primary">Create Course</button>
					<button type="button" class="btn btn-secondary clear-btn">Clear</button>
				</form>
			</div>

			<!-- Admin View: Class Creation Form -->
			<div class="form-section" id="class-section" style="display: none;">
				<h2>Create Class</h2>
				<form id="createClassForm" class="needs-validation" novalidate>
					<input type="hidden" name="action" value="create_class">
					<input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
					<div class="mb-3">
						<select name="course_id" class="form-control" required>
							<option value="">Select Course</option>
							<?php
							$stmt = $pdo->query("SELECT id, course_code, course_name FROM courses");
							while ($course = $stmt->fetch(PDO::FETCH_ASSOC)) {
								echo "<option value='{$course['id']}'>{$course['course_code']} - {$course['course_name']}</option>";
							}
							?>
						</select>
						<div class="invalid-feedback">Please select a course.</div>
					</div>
					<div class="mb-3">
						<select name="teacher_id" class="form-control" required>
							<option value="">Select Teacher</option>
							<?php
							$stmt = $pdo->query("SELECT td.user_id, td.full_name FROM teacher_details td JOIN users u ON td.user_id = u.id WHERE u.is_active = TRUE");
							while ($teacher = $stmt->fetch(PDO::FETCH_ASSOC)) {
								echo "<option value='{$teacher['user_id']}'>{$teacher['full_name']}</option>";
							}
							?>
						</select>
						<div class="invalid-feedback">Please select a teacher.</div>
					</div>
					<div class="mb-3">
						<input type="text" name="class_name" class="form-control" placeholder="Class Name (e.g., CS101 - Spring 2025)" required>
						<div class="invalid-feedback">Please enter a class name.</div>
					</div>
					<div class="mb-3">
						<input type="date" name="schedule_start_date" class="form-control" required>
						<div class="invalid-feedback">Please select a start date.</div>
					</div>
					<div class="mb-3">
						<input type="date" name="schedule_end_date" class="form-control" required>
						<div class="invalid-feedback">Please select an end date.</div>
					</div>
					<div class="mb-3">
						<input type="text" name="schedule_time" class="form-control" placeholder="Schedule Time (e.g., 09:00-10:30)" required>
						<div class="invalid-feedback">Please enter the schedule time.</div>
					</div>
					<div class="mb-3">
						<input type="text" name="room_number" class="form-control" placeholder="Room Number (e.g., Room 305)">
					</div>
					<div class="mb-3">
						<input type="number" name="capacity" class="form-control" placeholder="Capacity (e.g., 30)" required>
						<div class="invalid-feedback">Please enter the class capacity.</div>
					</div>
					<div class="mb-3">
						<textarea name="description" class="form-control" placeholder="Description"></textarea>
					</div>
					<button type="submit" class="btn btn-primary">Create Class</button>
					<button type="button" class="btn btn-secondary clear-btn">Clear</button>
				</form>
			</div>

			<!-- Admin View: Enrollment Creation Form -->
			<div class="form-section" id="enrollment-section" style="display: none;">
				<h2>Enroll Student</h2>
				<form id="createEnrollmentForm" class="needs-validation" novalidate>
					<input type="hidden" name="action" value="create_enrollment">
					<input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
					<div class="mb-3">
						<select name="student_id" class="form-control" required>
							<option value="">Select Student</option>
							<?php
							$stmt = $pdo->query("SELECT sd.id, sd.student_id, sd.full_name FROM student_details sd JOIN users u ON sd.user_id = u.id WHERE u.is_active = TRUE");
							while ($student = $stmt->fetch(PDO::FETCH_ASSOC)) {
								echo "<option value='{$student['id']}'>{$student['student_id']} - {$student['full_name']}</option>";
							}
							?>
						</select>
						<div class="invalid-feedback">Please select a student.</div>
					</div>
					<div class="mb-3">
						<select name="class_id" class="form-control" required>
							<option value="">Select Class</option>
							<?php
							$stmt = $pdo->query("SELECT c.id, c.class_name, c.room_number, c.schedule_time FROM class c JOIN courses co ON c.course_id = co.id");
							while ($class = $stmt->fetch(PDO::FETCH_ASSOC)) {
								echo "<option value='{$class['id']}'>{$class['class_name']} (Room: {$class['room_number']}, Time: {$class['schedule_time']})</option>";
							}
							?>
						</select>
						<div class="invalid-feedback">Please select a class.</div>
					</div>
					<button type="submit" class="btn btn-primary">Enroll Student</button>
					<button type="button" class="btn btn-secondary clear-btn">Clear</button>
				</form>
			</div>

			<!-- Admin View: Update Student Grades -->
			<div class="form-section" id="updateGrades-section" style="display: none;">
				<h2>Update Student Grades</h2>
				<form id="updateGradesForm" class="needs-validation" novalidate>
					<input type="hidden" name="action" value="update_grade">
					<input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
					<div class="mb-3">
						<input type="text" name="student_id" class="form-control" placeholder="Student ID" required>
						<div class="invalid-feedback">Please enter a student ID.</div>
					</div>
					<div class="mb-3">
						<select name="class_id" class="form-control" required>
							<option value="">Select Class</option>
							<?php
							$stmt = $pdo->query("SELECT c.id, c.class_name, co.course_name FROM class c JOIN courses co ON c.course_id = co.id");
							while ($class = $stmt->fetch(PDO::FETCH_ASSOC)) {
								echo "<option value='{$class['id']}'>{$class['course_name']} - {$class['class_name']}</option>";
							}
							?>
						</select>
						<div class="invalid-feedback">Please select a class.</div>
					</div>
					<div class="mb-3">
						<input type="number" name="score" class="form-control" placeholder="Score (0-100)" step="0.01" min="0" max="100" required>
						<div class="invalid-feedback">Please enter a valid score (0-100).</div>
					</div>
					<button type="submit" class="btn btn-primary">Update Grade</button>
					<button type="button" class="btn btn-secondary clear-btn">Clear</button>
				</form>
			</div>

			<!-- Admin View: View & Export Records -->
			<div id="edit-section" class="form-section" style="display: none;">
				<h2>View & Export Records</h2>
				<form id="editRecordsForm">
					<div class="row">
						<div class="col-md-6 mb-3">
							<label for="editTableSelector" class="form-label">Select Table:</label>
							<select id="editTableSelector" class="form-select" onchange="loadEditFieldOptions()">
								<option value="">Select a table</option>
								<option value="users">Users</option>
								<option value="student_details">Student Details</option>
								<option value="teacher_details">Teacher Details</option>
								<option value="courses">Courses</option>
								<option value="class">Classes</option>
								<option value="enrollments">Enrollments</option>
								<option value="grades">Grades</option>
							</select>
						</div>

						<!-- User active filter (only for users table) -->
						<div class="col-md-6 mb-3" id="userActiveFilterContainer" style="display: none;">
							<label for="userActiveFilter" class="form-label">Filter by Active Status:</label>
							<select id="userActiveFilter" class="form-select">
								<option value="">All Users</option>
								<option value="true">Active Users Only</option>
								<option value="false">Inactive Users Only</option>
							</select>
						</div>
					</div>

					<!-- Field Filter Dropdown -->
					<div class="mb-3">
						<label class="form-label">Select Fields to Display:</label>
						<div class="dropdown">
							<button class="btn btn-secondary dropdown-toggle" type="button" id="editFieldFilterDropdown" data-bs-toggle="dropdown" aria-expanded="false">
								Choose Fields
							</button>
							<ul class="dropdown-menu" id="editFieldFilterMenu" aria-labelledby="editFieldFilterDropdown">
								<!-- Checkboxes will be populated here by JS -->
							</ul>
						</div>
					</div>

					<div class="d-flex flex-wrap gap-2 mb-3">
						<button id="loadEditRecordsBtn" class="btn btn-primary" type="button" onclick="loadEditRecords(1)">View Records</button>
						<button id="resetEditFilterBtn" class="btn btn-secondary" type="button" onclick="resetEditFilters()">Reset Filters</button>
					</div>

					<!-- Export Buttons -->
					<div class="export-buttons">
						<label class="form-label">Export Data:</label>
						<div class="d-flex flex-wrap gap-2">
							<button type="button" class="btn btn-success" onclick="exportRecords('csv')">
								<i class="bi bi-file-earmark-spreadsheet"></i> Export as CSV
							</button>
							<button type="button" class="btn btn-success" onclick="exportRecords('excel')">
								<i class="bi bi-file-earmark-excel"></i> Export as Excel
							</button>
						</div>
						<small class="text-muted d-block mt-1">Note: Export will use the table and field selections above.</small>
					</div>
				</form>
				<div id="editRecordsOutput" class="table-section mt-4"></div>
			</div>

		<?php endif; ?>

		<?php if ($_SESSION['role'] === 'student' || $_SESSION['role'] === 'teacher'): ?>
			<!-- Student/Teacher View: Essential Information -->
			<div class="card p-4">
				<h2>Your Essential Information</h2>
				<?php
				$details = ($_SESSION['role'] === 'student') ? getStudentDetails() : getTeacherDetails();
				if ($details && is_array($details)) {
					echo "<p><strong>Full Name:</strong> " . htmlspecialchars($details['full_name'] ?? 'N/A') . "</p>";
					echo "<p><strong>Role:</strong> " . htmlspecialchars($_SESSION['role']) . "</p>";
					echo "<p><strong>Email:</strong> " . htmlspecialchars($details['email'] ?? 'N/A') . "</p>";

					if ($_SESSION['role'] === 'student') {
						// Overall GPA by Year
						echo "<h3>GPA by Academic Year</h3>";
						if (!empty($details['gpa_by_year'])) {
							echo "<ul>";
							foreach ($details['gpa_by_year'] as $yearData) {
								echo "<li><strong>" . htmlspecialchars($yearData['academic_year']) . ":</strong> GPA " . number_format($yearData['year_gpa'], 2) . " (Based on " . $yearData['grade_count'] . " grades)</li>";
							}
							echo "</ul>";
						} else {
							echo "<p>No GPA data available yet.</p>";
						}

						// Subject Grades
						echo "<h3>Grades by Subject</h3>";
						if (!empty($details['subject_grades'])) {
							echo "<ul>";
							foreach ($details['subject_grades'] as $grade) {
								echo "<li><strong>" . htmlspecialchars($grade['course_name']) . " (" . htmlspecialchars($grade['class_name']) . "):</strong> Score " . htmlspecialchars($grade['score']) . "%, Grade " . htmlspecialchars($grade['grade']) . " (GPA " . htmlspecialchars($grade['gpa_value']) . ")</li>";
							}
							echo "</ul>";
						} else {
							echo "<p>No subject grades recorded yet.</p>";
						}

						// Enrolled Classes (Essential Info)
						$enrollmentCount = !empty($details['enrollments']) ? count($details['enrollments']) : 0;
						echo "<p><strong>Enrolled Classes:</strong> " . $enrollmentCount . "</p>";
						if ($enrollmentCount > 0) {
							$firstEnrollment = $details['enrollments'][0];
							echo "<p><strong>Example Class:</strong> " . htmlspecialchars($firstEnrollment['course_name']) . " - " . htmlspecialchars($firstEnrollment['class_name']) . " (Status: " . htmlspecialchars($firstEnrollment['status']) . ")</p>";
						} else {
							echo "<p>No enrolled classes found.</p>";
						}
					} elseif ($_SESSION['role'] === 'teacher') {
						// Teacher info remains unchanged for now
						$classCount = !empty($details['classes']) ? count($details['classes']) : 0;
						echo "<p><strong>Classes Teaching:</strong> " . $classCount . "</p>";
						if ($classCount > 0) {
							$firstClass = $details['classes'][0];
							echo "<p><strong>Example Class:</strong> " . htmlspecialchars($firstClass['course_name']) . " - " . htmlspecialchars($firstClass['class_name']) . " (Schedule: " . htmlspecialchars($firstClass['schedule_time']) . ")</p>";
						} else {
							echo "<p>No classes assigned.</p>";
						}
					}
				} else {
					echo "<p>No details available.</p>";
				}
				?>
			</div>
		<?php endif; ?>

		<!-- Dialog Boxes -->
		<div id="passwordPromptDialog" class="modal fade" tabindex="-1" aria-labelledby="passwordPromptLabel" aria-hidden="true">
			<div class="modal-dialog modal-dialog-centered">
				<div class="modal-content">
					<div class="modal-header">
						<h5 class="modal-title" id="passwordPromptLabel">Password Reset</h5>
						<button type="button" class="btn-close" aria-label="Close"></button>
					</div>
					<div class="modal-body">
						<p>Enter your current password to generate a reset token:</p>
						<input type="password" id="currentPasswordInput" class="form-control" placeholder="Current Password" required>
					</div>
					<div class="modal-footer">
						<button type="button" class="btn btn-primary" id="submitPasswordBtn">Submit</button>
						<button type="button" class="btn btn-secondary" id="cancelPasswordBtn">Cancel</button>
					</div>
				</div>
			</div>
		</div>

		<div id="resetTokenDialog" class="modal fade" tabindex="-1" aria-labelledby="resetTokenLabel" aria-hidden="true">
			<div class="modal-dialog modal-dialog-centered">
				<div class="modal-content">
					<div class="modal-header">
						<h5 class="modal-title" id="resetTokenLabel">Reset Token</h5>
						<button type="button" class="btn-close" aria-label="Close"></button>
					</div>
					<div class="modal-body">
						<p id="resetTokenMessage"></p>
						<input type="text" id="resetTokenInput" class="form-control" readonly>
					</div>
					<div class="modal-footer">
						<button type="button" class="btn btn-primary" id="copyTokenBtn">Copy Token</button>
						<button type="button" class="btn btn-success" id="resetNowBtn">Reset Now</button>
						<button type="button" class="btn btn-secondary" id="closeTokenBtn">Close</button>
					</div>
				</div>
			</div>
		</div>

		<div id="adminContactDialog" class="modal fade" tabindex="-1" aria-labelledby="adminContactLabel" aria-hidden="true">
			<div class="modal-dialog modal-dialog-centered">
				<div class="modal-content">
					<div class="modal-header">
						<h5 class="modal-title" id="adminContactLabel">Contact Admin</h5>
						<button type="button" class="btn-close" aria-label="Close"></button>
					</div>
					<div class="modal-body">
						<p id="adminContactMessage"></p>
					</div>
					<div class="modal-footer">
						<button type="button" class="btn btn-secondary" id="closeAdminBtn">Close</button>
					</div>
				</div>
			</div>
		</div>
	</div>

	<!-- Pass PHP variables to JavaScript -->
	<script>
		window.csrfToken = '<?php echo htmlspecialchars($csrf_token); ?>';
		window.userRole = '<?php echo $_SESSION['role']; ?>';
		window.adminEmails = `<?php
								$stmt = $pdo->prepare("SELECT username, email FROM users WHERE role = 'admin' AND is_active = TRUE");
								$stmt->execute();
								$admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
								$adminLinks = '';
								foreach ($admins as $admin) {
									$adminLinks .= "<a href=\"mailto:" . htmlspecialchars($admin['email']) . "\">" . htmlspecialchars($admin['username']) . " (" . htmlspecialchars($admin['email']) . ")</a></br>";
								}
								echo addslashes($adminLinks);
								?>`;
	</script>

	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
	<script src="includes/script.js"></script>
</body>

</html>