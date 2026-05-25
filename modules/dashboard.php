<?php
// ============================================================
//  modules/dashboard.php  (was index.php in root)
//  FIX: all three roles use ref_id as their respective IDs
//  FIX: consistent require_once paths
// ============================================================
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/db.php';

// ═══════════════════════════════════════════════════════════
//  ADMIN DASHBOARD
// ═══════════════════════════════════════════════════════════
if (isAdmin()) {
    $total_students  = $conn->query("SELECT COUNT(*) AS c FROM students  WHERE status='Active'")->fetch_assoc()['c'];
    $total_faculty   = $conn->query("SELECT COUNT(*) AS c FROM faculty   WHERE status='Active'")->fetch_assoc()['c'];
    $total_courses   = $conn->query("SELECT COUNT(*) AS c FROM courses")->fetch_assoc()['c'];
    $total_depts     = $conn->query("SELECT COUNT(*) AS c FROM departments")->fetch_assoc()['c'];
    $pending_fees    = $conn->query("SELECT COUNT(*) AS c FROM fees WHERE status IN ('Pending','Overdue')")->fetch_assoc()['c'];
    $total_enroll    = $conn->query("SELECT COUNT(*) AS c FROM enrollments WHERE status='Enrolled'")->fetch_assoc()['c'];

    $recent_students = $conn->query("
        SELECT s.*, d.dept_code
        FROM students s
        LEFT JOIN departments d ON s.dept_id = d.dept_id
        ORDER BY s.created_at DESC LIMIT 5
    ");
    $recent_fees     = $conn->query("
        SELECT f.*, CONCAT(s.first_name,' ',s.last_name) AS student_name
        FROM fees f
        JOIN students s ON f.student_id = s.student_id
        ORDER BY f.created_at DESC LIMIT 5
    ");
}

// ═══════════════════════════════════════════════════════════
//  FACULTY DASHBOARD
// ═══════════════════════════════════════════════════════════
if (isFaculty()) {
    // FIX: ref_id is faculty_id directly
    $my_fac_id = intval($_SESSION['ref_id'] ?? 0);

    $total_my_courses   = $conn->query("SELECT COUNT(*) AS c FROM courses WHERE faculty_id=$my_fac_id")->fetch_assoc()['c'];
    $total_my_students  = $conn->query("
        SELECT COUNT(DISTINCT e.student_id) AS c
        FROM enrollments e
        JOIN courses c ON e.course_id = c.course_id
        WHERE c.faculty_id=$my_fac_id AND e.status='Enrolled'
    ")->fetch_assoc()['c'];
    $total_grades_given = $conn->query("
        SELECT COUNT(*) AS c
        FROM grades g
        JOIN enrollments e ON g.enrollment_id = e.enrollment_id
        JOIN courses     c ON e.course_id     = c.course_id
        WHERE c.faculty_id=$my_fac_id
    ")->fetch_assoc()['c'];

    $my_courses = $conn->query("
        SELECT c.*, d.dept_code,
               (SELECT COUNT(*) FROM enrollments WHERE course_id=c.course_id AND status='Enrolled') AS enrolled
        FROM courses c
        LEFT JOIN departments d ON c.dept_id = d.dept_id
        WHERE c.faculty_id=$my_fac_id
        ORDER BY c.course_name
    ");
}

// ═══════════════════════════════════════════════════════════
//  STUDENT DASHBOARD
// ═══════════════════════════════════════════════════════════
if (isStudent()) {
    // FIX: ref_id is student_id directly
    $my_stu_id = intval($_SESSION['ref_id'] ?? 0);

    $my_profile = $conn->query("
        SELECT s.*, d.dept_name
        FROM students s
        LEFT JOIN departments d ON s.dept_id = d.dept_id
        WHERE s.student_id = $my_stu_id
    ")->fetch_assoc();

    $total_enrolled  = $conn->query("SELECT COUNT(*) AS c FROM enrollments WHERE student_id=$my_stu_id AND status='Enrolled'")->fetch_assoc()['c'];
    $total_grades    = $conn->query("
        SELECT COUNT(*) AS c FROM grades g
        JOIN enrollments e ON g.enrollment_id = e.enrollment_id
        WHERE e.student_id = $my_stu_id
    ")->fetch_assoc()['c'];
    $pending_my_fees = $conn->query("SELECT COUNT(*) AS c FROM fees WHERE student_id=$my_stu_id AND status IN ('Pending','Overdue')")->fetch_assoc()['c'];
    $avg_grade       = $conn->query("
        SELECT ROUND(AVG(g.grade_points), 2) AS avg
        FROM grades g
        JOIN enrollments e ON g.enrollment_id = e.enrollment_id
        WHERE e.student_id = $my_stu_id
    ")->fetch_assoc()['avg'];

    $my_grades = $conn->query("
        SELECT g.*, c.course_code, c.course_name
        FROM grades g
        JOIN enrollments e ON g.enrollment_id = e.enrollment_id
        JOIN courses     c ON e.course_id     = c.course_id
        WHERE e.student_id = $my_stu_id
        ORDER BY g.exam_date DESC LIMIT 5
    ");
    $my_fees = $conn->query("
        SELECT * FROM fees
        WHERE student_id = $my_stu_id
        ORDER BY created_at DESC LIMIT 5
    ");
}
?>

<div class="page-header">
    <h2><i class="fas fa-tachometer-alt"></i> Dashboard</h2>
    <span style="color:#999;font-size:0.9em;"><?= date('l, F j, Y') ?></span>
</div>

<!-- ══════════════════════════════════════════
     ADMIN VIEW
     ══════════════════════════════════════════ -->
<?php if (isAdmin()): ?>

<div class="stats-grid">
    <div class="stat-card blue">
        <div class="stat-icon"><i class="fas fa-user-graduate"></i></div>
        <div class="stat-info"><h3><?= $total_students ?></h3><p>Active Students</p></div>
    </div>
    <div class="stat-card green">
        <div class="stat-icon"><i class="fas fa-chalkboard-teacher"></i></div>
        <div class="stat-info"><h3><?= $total_faculty ?></h3><p>Faculty Members</p></div>
    </div>
    <div class="stat-card orange">
        <div class="stat-icon"><i class="fas fa-book"></i></div>
        <div class="stat-info"><h3><?= $total_courses ?></h3><p>Total Courses</p></div>
    </div>
    <div class="stat-card purple">
        <div class="stat-icon"><i class="fas fa-building"></i></div>
        <div class="stat-info"><h3><?= $total_depts ?></h3><p>Departments</p></div>
    </div>
    <div class="stat-card red">
        <div class="stat-icon"><i class="fas fa-exclamation-circle"></i></div>
        <div class="stat-info"><h3><?= $pending_fees ?></h3><p>Pending Fees</p></div>
    </div>
    <div class="stat-card teal">
        <div class="stat-icon"><i class="fas fa-clipboard-list"></i></div>
        <div class="stat-info"><h3><?= $total_enroll ?></h3><p>Active Enrollments</p></div>
    </div>
</div>

<div class="dashboard-grid">
    <div class="card">
        <div class="card-header">
            <h3 class="section-title"><i class="fas fa-user-graduate"></i> Recent Students</h3>
            <a href="/students" class="btn btn-primary btn-sm">View All</a>
        </div>
        <div class="table-container">
            <table>
                <thead>
                    <tr><th>Student ID</th><th>Name</th><th>Dept</th><th>Status</th></tr>
                </thead>
                <tbody>
                <?php
                $has = false;
                while ($s = $recent_students->fetch_assoc()):
                    $has = true; ?>
                <tr>
                    <td><strong><?= htmlspecialchars($s['student_id']) ?></strong></td>
                    <td><?= htmlspecialchars($s['first_name'] . ' ' . $s['last_name']) ?></td>
                    <td><span class="badge badge-info"><?= $s['dept_code'] ?? 'N/A' ?></span></td>
                    <td><span class="badge badge-success"><?= $s['status'] ?></span></td>
                </tr>
                <?php endwhile;
                if (!$has): ?>
                <tr><td colspan="4" style="text-align:center;color:#999;padding:15px;">No students yet.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="section-title"><i class="fas fa-money-bill-wave"></i> Recent Fee Records</h3>
            <a href="/fees" class="btn btn-primary btn-sm">View All</a>
        </div>
        <div class="table-container">
            <table>
                <thead>
                    <tr><th>Student</th><th>Type</th><th>Amount</th><th>Status</th></tr>
                </thead>
                <tbody>
                <?php
                $has = false;
                while ($f = $recent_fees->fetch_assoc()):
                    $has = true;
                    $cls = match($f['status']) {
                        'Paid'    => 'badge-success',
                        'Partial' => 'badge-warning',
                        'Overdue' => 'badge-danger',
                        default   => 'badge-secondary'
                    }; ?>
                <tr>
                    <td><?= htmlspecialchars($f['student_name']) ?></td>
                    <td><?= htmlspecialchars($f['fee_type']) ?></td>
                    <td>৳<?= number_format($f['amount']) ?></td>
                    <td><span class="badge <?= $cls ?>"><?= $f['status'] ?></span></td>
                </tr>
                <?php endwhile;
                if (!$has): ?>
                <tr><td colspan="4" style="text-align:center;color:#999;padding:15px;">No fee records yet.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════
     FACULTY VIEW
     ══════════════════════════════════════════ -->
<?php elseif (isFaculty()): ?>

<div class="stats-grid">
    <div class="stat-card blue">
        <div class="stat-icon"><i class="fas fa-book"></i></div>
        <div class="stat-info"><h3><?= $total_my_courses ?></h3><p>My Courses</p></div>
    </div>
    <div class="stat-card green">
        <div class="stat-icon"><i class="fas fa-user-graduate"></i></div>
        <div class="stat-info"><h3><?= $total_my_students ?></h3><p>My Students</p></div>
    </div>
    <div class="stat-card orange">
        <div class="stat-icon"><i class="fas fa-graduation-cap"></i></div>
        <div class="stat-info"><h3><?= $total_grades_given ?></h3><p>Grades Given</p></div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="section-title"><i class="fas fa-book"></i> My Courses</h3>
        <a href="/courses" class="btn btn-primary btn-sm">View All</a>
    </div>
    <div class="table-container">
        <table>
            <thead>
                <tr><th>Code</th><th>Course Name</th><th>Dept</th><th>Semester</th><th>Students</th></tr>
            </thead>
            <tbody>
            <?php
            $has = false;
            while ($c = $my_courses->fetch_assoc()):
                $has = true; ?>
            <tr>
                <td><strong><?= htmlspecialchars($c['course_code']) ?></strong></td>
                <td><?= htmlspecialchars($c['course_name']) ?></td>
                <td><span class="badge badge-info"><?= $c['dept_code'] ?? 'N/A' ?></span></td>
                <td>Sem <?= $c['semester'] ?></td>
                <td><?= $c['enrolled'] ?> students</td>
            </tr>
            <?php endwhile;
            if (!$has): ?>
            <tr><td colspan="5" style="text-align:center;color:#999;padding:15px;">No courses assigned yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ══════════════════════════════════════════
     STUDENT VIEW
     ══════════════════════════════════════════ -->
<?php elseif (isStudent()): ?>

<?php if ($my_profile): ?>
<div class="card" style="margin-bottom:20px;background:linear-gradient(135deg,#1a237e,#3949ab);color:#fff;">
    <div style="display:flex;align-items:center;gap:20px;">
        <div style="font-size:4em;opacity:0.8;"><i class="fas fa-user-circle"></i></div>
        <div>
            <h2 style="margin:0;font-size:1.5em;">
                <?= htmlspecialchars($my_profile['first_name'] . ' ' . $my_profile['last_name']) ?>
            </h2>
            <p style="margin:4px 0;opacity:0.85;">
                Roll No: <strong><?= htmlspecialchars($my_profile['roll_no']) ?></strong>
            </p>
            <p style="margin:4px 0;opacity:0.85;">
                Department: <strong><?= htmlspecialchars($my_profile['dept_name'] ?? 'N/A') ?></strong>
                &nbsp;|&nbsp; Semester: <strong><?= $my_profile['semester'] ?></strong>
            </p>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="stats-grid">
    <div class="stat-card blue">
        <div class="stat-icon"><i class="fas fa-book"></i></div>
        <div class="stat-info"><h3><?= $total_enrolled ?></h3><p>Enrolled Courses</p></div>
    </div>
    <div class="stat-card green">
        <div class="stat-icon"><i class="fas fa-graduation-cap"></i></div>
        <div class="stat-info"><h3><?= $total_grades ?></h3><p>Grades Received</p></div>
    </div>
    <div class="stat-card orange">
        <div class="stat-icon"><i class="fas fa-star"></i></div>
        <div class="stat-info"><h3><?= $avg_grade ?? 'N/A' ?></h3><p>Avg Grade Points</p></div>
    </div>
    <div class="stat-card red">
        <div class="stat-icon"><i class="fas fa-exclamation-circle"></i></div>
        <div class="stat-info"><h3><?= $pending_my_fees ?></h3><p>Pending Fees</p></div>
    </div>
</div>

<div class="dashboard-grid">
    <div class="card">
        <div class="card-header">
            <h3 class="section-title"><i class="fas fa-graduation-cap"></i> My Recent Grades</h3>
            <a href="/grades" class="btn btn-primary btn-sm">View All</a>
        </div>
        <div class="table-container">
            <table>
                <thead>
                    <tr><th>Course</th><th>Marks</th><th>Grade</th><th>Points</th></tr>
                </thead>
                <tbody>
                <?php
                $has = false;
                while ($g = $my_grades->fetch_assoc()):
                    $has = true;
                    $cls = $g['grade_letter'] === 'F'
                        ? 'badge-danger'
                        : ($g['grade_points'] >= 3.5 ? 'badge-success' : 'badge-warning'); ?>
                <tr>
                    <td><?= htmlspecialchars($g['course_code']) ?></td>
                    <td><?= $g['marks_obtained'] ?>/<?= $g['total_marks'] ?></td>
                    <td><span class="badge <?= $cls ?>"><?= $g['grade_letter'] ?></span></td>
                    <td><?= $g['grade_points'] ?></td>
                </tr>
                <?php endwhile;
                if (!$has): ?>
                <tr><td colspan="4" style="text-align:center;color:#999;padding:15px;">No grades yet.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="section-title"><i class="fas fa-money-bill-wave"></i> My Fee Status</h3>
            <a href="/fees" class="btn btn-primary btn-sm">View All</a>
        </div>
        <div class="table-container">
            <table>
                <thead>
                    <tr><th>Fee Type</th><th>Amount</th><th>Paid</th><th>Status</th></tr>
                </thead>
                <tbody>
                <?php
                $has = false;
                while ($f = $my_fees->fetch_assoc()):
                    $has = true;
                    $cls = match($f['status']) {
                        'Paid'    => 'badge-success',
                        'Partial' => 'badge-warning',
                        'Overdue' => 'badge-danger',
                        default   => 'badge-secondary'
                    }; ?>
                <tr>
                    <td><?= htmlspecialchars($f['fee_type']) ?></td>
                    <td>৳<?= number_format($f['amount']) ?></td>
                    <td>৳<?= number_format($f['paid_amount']) ?></td>
                    <td><span class="badge <?= $cls ?>"><?= $f['status'] ?></span></td>
                </tr>
                <?php endwhile;
                if (!$has): ?>
                <tr><td colspan="4" style="text-align:center;color:#999;padding:15px;">No fee records.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>