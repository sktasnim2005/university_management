<?php
// ============================================================
//  modules/enrollments.php
//  Professional Version
//  - Clean error handling
//  - Auto-hide alerts
//  - Duplicate prevention
//  - Admin only access
// ============================================================

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/db.php';

// ── ADMIN CHECK ──────────────────────────────────────────────
if (!isAdmin()) {
    die("
    <div class='alert alert-danger' style='margin:20px'>
        <i class='fas fa-ban'></i>
        ⛔ Only admins can manage enrollments.
    </div>
    <p style='margin:10px 20px'>
        <a href='/dashboard'>← Back to Dashboard</a>
    </p>
    ");
}

$msg = $err = '';


// ── DELETE ───────────────────────────────────────────────────
if (isset($_GET['delete'])) {

    $id = intval($_GET['delete']);

    try {

        $stmt = $conn->prepare(
            "DELETE FROM enrollments WHERE enrollment_id=?"
        );

        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            $msg = "Enrollment removed successfully!";
        }

    } catch (mysqli_sql_exception $e) {

        $err = "Unable to delete enrollment.";

        error_log($e->getMessage());
    }
}


// ── INSERT ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $sid    = intval($_POST['student_id']);
    $cid    = intval($_POST['course_id']);
    $date   = trim($_POST['enrollment_date']);
    $status = trim($_POST['status']);

    try {

        // Check duplicate enrollment
        $check = $conn->prepare(
            "SELECT enrollment_id
             FROM enrollments
             WHERE student_id=? AND course_id=?"
        );

        $check->bind_param("ii", $sid, $cid);
        $check->execute();

        $result = $check->get_result();

        if ($result->num_rows > 0) {

            $err = "This student is already enrolled in that course.";

        } else {

            // Insert enrollment
            $stmt = $conn->prepare(
                "INSERT INTO enrollments
                (student_id, course_id, enrollment_date, status)
                 VALUES (?, ?, ?, ?)"
            );

            $stmt->bind_param(
                "iiss",
                $sid,
                $cid,
                $date,
                $status
            );

            if ($stmt->execute()) {
                $msg = "Student enrolled successfully!";
            }
        }

    } catch (mysqli_sql_exception $e) {

        switch ($e->getCode()) {

            // Duplicate entry
            case 1062:
                $err = "This enrollment already exists.";
                break;

            // Required field missing
            case 1048:
                $err = "Please fill all required fields.";
                break;

            // Foreign key error
            case 1452:
                $err = "Invalid student or course selected.";
                break;

            // Data too long
            case 1406:
                $err = "One or more fields contain too much text.";
                break;

            default:
                $err = "Unable to process enrollment. Please try again.";
        }

        // Log actual error privately
        error_log($e->getMessage());
    }
}


// ── DATA FETCH ───────────────────────────────────────────────
$enrollments = $conn->query("
    SELECT e.*,
           CONCAT(s.first_name,' ',s.last_name) AS student_name,
           s.roll_no,
           c.course_name,
           c.course_code,
           d.dept_code
    FROM enrollments e
    JOIN students s
        ON e.student_id = s.student_id
    JOIN courses c
        ON e.course_id = c.course_id
    LEFT JOIN departments d
        ON c.dept_id = d.dept_id
    ORDER BY e.enrollment_id DESC
");


// ── STUDENTS ─────────────────────────────────────────────────
$students = [];

$sr = $conn->query("
    SELECT student_id,
           CONCAT(roll_no,' - ',first_name,' ',last_name) AS sname
    FROM students
    WHERE status='Active'
    ORDER BY roll_no
");

while ($s = $sr->fetch_assoc()) {
    $students[] = $s;
}


// ── COURSES ──────────────────────────────────────────────────
$courses = [];

$cr = $conn->query("
    SELECT course_id,
           CONCAT(course_code,' - ',course_name) AS cname
    FROM courses
    ORDER BY course_code
");

while ($c = $cr->fetch_assoc()) {
    $courses[] = $c;
}
?>



<div class="page-header">
    <h2>
        <i class="fas fa-clipboard-list"></i>
        Enrollment Management
    </h2>

    <button class="btn btn-primary"
            onclick="document.getElementById('enrollModal').classList.add('active')">
        <i class="fas fa-plus"></i>
        Enroll Student
    </button>
</div>



<!-- ALERTS -->
<?php if ($msg): ?>
<div class="alert alert-success auto-hide-alert">
    <i class="fas fa-check-circle"></i>
    <?= htmlspecialchars($msg) ?>
</div>
<?php endif; ?>

<?php if ($err): ?>
<div class="alert alert-danger auto-hide-alert">
    <i class="fas fa-exclamation-circle"></i>
    <?= htmlspecialchars($err) ?>
</div>
<?php endif; ?>


<script>
setTimeout(() => {

    document.querySelectorAll('.auto-hide-alert').forEach(alertBox => {

        alertBox.style.transition = "opacity 0.5s ease";
        alertBox.style.opacity = "0";

        setTimeout(() => {
            alertBox.remove();
        }, 500);

    });

}, 3000);
</script>



<div class="card">

    <div class="card-header">
        <h3>
            All Enrollments
            (<?= $enrollments->num_rows ?>)
        </h3>
    </div>

    <div class="table-container">

        <table>

            <thead>
                <tr>
                    <th>#</th>
                    <th>Roll No</th>
                    <th>Student</th>
                    <th>Course</th>
                    <th>Dept</th>
                    <th>Date</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>

            <tbody>

            <?php
            $i = 1;

            while ($e = $enrollments->fetch_assoc()):

                $cls = match($e['status']) {

                    'Enrolled'  => 'badge-success',
                    'Completed' => 'badge-info',
                    default     => 'badge-danger'
                };
            ?>

            <tr>

                <td><?= $i++ ?></td>

                <td>
                    <?= htmlspecialchars($e['roll_no']) ?>
                </td>

                <td>
                    <?= htmlspecialchars($e['student_name']) ?>
                </td>

                <td>
                    <?= htmlspecialchars(
                        $e['course_code'] . ' - ' . $e['course_name']
                    ) ?>
                </td>

                <td>
                    <span class="badge badge-info">
                        <?= $e['dept_code'] ?? '' ?>
                    </span>
                </td>

                <td>
                    <?= htmlspecialchars($e['enrollment_date']) ?>
                </td>

                <td>
                    <span class="badge <?= $cls ?>">
                        <?= htmlspecialchars($e['status']) ?>
                    </span>
                </td>

                <td>

                    <a href="?delete=<?= $e['enrollment_id'] ?>"
                       class="btn btn-danger btn-sm"
                       onclick="return confirm('Remove this enrollment?')">

                        <i class="fas fa-trash"></i>

                    </a>

                </td>

            </tr>

            <?php endwhile; ?>

            </tbody>

        </table>

    </div>

</div>



<!-- MODAL -->
<div class="modal-overlay" id="enrollModal">

<div class="modal">

    <div class="modal-header">

        <h3>Enroll Student in Course</h3>

        <button class="modal-close"
                onclick="document.getElementById('enrollModal').classList.remove('active')">

            &times;

        </button>

    </div>


    <form method="POST">

        <div class="form-grid">

            <div class="form-group">

                <label>Student *</label>

                <select name="student_id" required>

                    <option value="">Select Student</option>

                    <?php foreach ($students as $s): ?>

                    <option value="<?= $s['student_id'] ?>">

                        <?= htmlspecialchars($s['sname']) ?>

                    </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <div class="form-group">

                <label>Course *</label>

                <select name="course_id" required>

                    <option value="">Select Course</option>

                    <?php foreach ($courses as $c): ?>

                    <option value="<?= $c['course_id'] ?>">

                        <?= htmlspecialchars($c['cname']) ?>

                    </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <div class="form-group">

                <label>Enrollment Date</label>

                <input type="date"
                       name="enrollment_date"
                       value="<?= date('Y-m-d') ?>">

            </div>


            <div class="form-group">

                <label>Status</label>

                <select name="status">

                    <option value="Enrolled">Enrolled</option>
                    <option value="Dropped">Dropped</option>
                    <option value="Completed">Completed</option>

                </select>

            </div>

        </div>


        <div class="form-actions">

            <button type="submit" class="btn btn-success">

                <i class="fas fa-save"></i>
                Enroll

            </button>

            <button type="button"
                    class="btn"
                    style="background:#ddd;color:#333"
                    onclick="document.getElementById('enrollModal').classList.remove('active')">

                Cancel

            </button>

        </div>

    </form>

</div>
</div>


<?php require_once __DIR__ . '/../includes/footer.php'; ?>