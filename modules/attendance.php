<?php
// ============================================================
//  modules/attendance.php
//  FIX: student view uses student_id from ref_id directly
//  FIX: consistent require_once paths
// ============================================================
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/db.php';

$msg = $err = '';

// ── MARK ATTENDANCE (admin / faculty only) ───────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_attendance'])) {
    blockIf(isStudent(), 'Students cannot mark attendance!');

    $course_id    = intval($_POST['course_id']);
    $date         = $conn->real_escape_string($_POST['att_date']);
    $students_att = $_POST['attendance'] ?? [];

    $all   = $conn->query(
        "SELECT student_id FROM enrollments
         WHERE course_id = $course_id AND status = 'Enrolled'"
    );
    $saved = 0;

    while ($s = $all->fetch_assoc()) {
        $sid    = $s['student_id'];
        $status = $students_att[$sid] ?? 'Absent';
        $stmt   = $conn->prepare(
            "INSERT INTO attendance (student_id, course_id, attendance_date, status)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE status = ?"
        );
        $stmt->bind_param('iisss', $sid, $course_id, $date, $status, $status);
        $stmt->execute();
        $saved++;
    }
    $msg = "Attendance marked for $saved students on $date.";
}

// ── SHARED VARS ──────────────────────────────────────────────
$courses    = $conn->query(
    "SELECT c.*, d.dept_code FROM courses c
     LEFT JOIN departments d ON c.dept_id = d.dept_id
     ORDER BY c.course_name"
);
$sel_course = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;
$sel_date   = isset($_GET['att_date'])  ? $_GET['att_date']          : date('Y-m-d');

// ── STUDENT VIEW — own attendance summary ────────────────────
$summary = null;
if (isStudent()) {
    // FIX: ref_id stores student_id directly
    $my_id  = intval($_SESSION['ref_id'] ?? 0);
    $summary = $conn->query("
        SELECT c.course_code, c.course_name,
               COUNT(CASE WHEN a.status='Present' THEN 1 END)  AS present_cnt,
               COUNT(a.attendance_id)                           AS total_cls,
               ROUND(
                   COUNT(CASE WHEN a.status='Present' THEN 1 END) * 100.0
                   / NULLIF(COUNT(a.attendance_id), 0),
               1) AS pct
        FROM courses c
        JOIN enrollments e  ON c.course_id = e.course_id AND e.student_id = $my_id
        LEFT JOIN attendance a ON c.course_id = a.course_id AND a.student_id = $my_id
        GROUP BY c.course_id
        ORDER BY c.course_name
    ");
}

// ── ADMIN / FACULTY VIEW — mark attendance ───────────────────
$enrolled_students = [];
$att_summary       = null;

if ($sel_course && !isStudent()) {
    $q = $conn->query("
        SELECT s.*,
               a.status AS att_status
        FROM students s
        JOIN enrollments e  ON s.student_id = e.student_id
        LEFT JOIN attendance a
            ON s.student_id = a.student_id
           AND a.course_id  = $sel_course
           AND a.attendance_date = '$sel_date'
        WHERE e.course_id = $sel_course AND e.status = 'Enrolled'
    ");
    while ($r = $q->fetch_assoc()) $enrolled_students[] = $r;

    $att_summary = $conn->query("
        SELECT s.roll_no,
               CONCAT(s.first_name,' ',s.last_name) AS name,
               COUNT(CASE WHEN a.status='Present' THEN 1 END)  AS present_cnt,
               COUNT(a.attendance_id)                           AS total_cls,
               ROUND(
                   COUNT(CASE WHEN a.status='Present' THEN 1 END) * 100.0
                   / NULLIF(COUNT(a.attendance_id), 0),
               1) AS pct
        FROM students s
        JOIN enrollments e ON s.student_id = e.student_id AND e.course_id = $sel_course
        LEFT JOIN attendance a ON s.student_id = a.student_id AND a.course_id = $sel_course
        WHERE e.status = 'Enrolled'
        GROUP BY s.student_id
        ORDER BY pct DESC
    ");
}
?>

<div class="page-header">
    <h2>
        <i class="fas fa-clipboard-check"></i> Attendance
        <?php if (isStudent()): ?>
        <span style="font-size:0.6em;color:#999">— My Attendance</span>
        <?php endif; ?>
    </h2>
</div>

<?php if ($msg): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($err) ?></div><?php endif; ?>

<!-- ══════════════════════════════════════════════════════════
     STUDENT VIEW
     ══════════════════════════════════════════════════════════ -->
<?php if (isStudent()): ?>
<div class="card">
    <div class="card-header"><h3>My Attendance Summary</h3></div>
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Course Code</th><th>Course Name</th>
                    <th>Present</th><th>Total Classes</th><th>Attendance %</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $has_rows = false;
            while ($r = $summary->fetch_assoc()):
                $has_rows = true;
                $pct = $r['pct'] ?? 0;
                $cls = $pct >= 75 ? 'badge-success' : ($pct >= 50 ? 'badge-warning' : 'badge-danger');
            ?>
            <tr>
                <td><strong><?= htmlspecialchars($r['course_code']) ?></strong></td>
                <td><?= htmlspecialchars($r['course_name']) ?></td>
                <td><?= $r['present_cnt'] ?></td>
                <td><?= $r['total_cls'] ?></td>
                <td>
                    <span class="badge <?= $cls ?>"><?= $pct ?>%</span>
                    <?php if ($pct < 75): ?>
                    <span style="color:#c62828;font-size:0.8em;"> ⚠️ Low!</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endwhile; ?>
            <?php if (!$has_rows): ?>
            <tr>
                <td colspan="5" style="text-align:center;color:#999;padding:20px;">
                    No attendance records found.
                </td>
            </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     ADMIN / FACULTY VIEW
     ══════════════════════════════════════════════════════════ -->
<?php else: ?>

<!-- Course + Date selector -->
<div class="card" style="margin-bottom:20px;">
    <div class="card-header"><h3>Select Course &amp; Date</h3></div>
    <form method="GET" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;">
        <div class="form-group" style="min-width:280px;">
            <label>Course</label>
            <select name="course_id">
                <option value="">-- Select Course --</option>
                <?php $courses->data_seek(0); while ($c = $courses->fetch_assoc()): ?>
                <option value="<?= $c['course_id'] ?>"
                    <?= $sel_course == $c['course_id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($c['course_code'] . ' - ' . $c['course_name']) ?>
                </option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Date</label>
            <input type="date" name="att_date" value="<?= $sel_date ?>">
        </div>
        <button type="submit" class="btn btn-primary">
            <i class="fas fa-search"></i> Load
        </button>
    </form>
</div>

<?php if ($sel_course && count($enrolled_students) > 0): ?>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">

    <!-- Mark Attendance -->
    <div class="card">
        <div class="card-header">
            <h3>Mark Attendance — <?= htmlspecialchars($sel_date) ?></h3>
        </div>
        <form method="POST">
            <input type="hidden" name="mark_attendance" value="1">
            <input type="hidden" name="course_id"       value="<?= $sel_course ?>">
            <input type="hidden" name="att_date"        value="<?= $sel_date ?>">
            <div style="margin-bottom:10px;display:flex;gap:8px;">
                <button type="button" class="btn btn-success btn-sm"
                        onclick="setAll('Present')">
                    <i class="fas fa-check"></i> All Present
                </button>
                <button type="button" class="btn btn-danger btn-sm"
                        onclick="setAll('Absent')">
                    <i class="fas fa-times"></i> All Absent
                </button>
            </div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr><th>Roll No</th><th>Name</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($enrolled_students as $s):
                    $att = $s['att_status'] ?? 'Present'; ?>
                    <tr>
                        <td><?= htmlspecialchars($s['roll_no']) ?></td>
                        <td><?= htmlspecialchars($s['first_name'] . ' ' . $s['last_name']) ?></td>
                        <td>
                            <select name="attendance[<?= $s['student_id'] ?>]"
                                    class="att-select"
                                    style="padding:4px 8px;border-radius:4px;border:1px solid #ddd;">
                                <?php foreach (['Present','Absent','Late'] as $st): ?>
                                <option value="<?= $st ?>"
                                    <?= $att === $st ? 'selected' : '' ?>>
                                    <?= $st ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="form-actions" style="margin-top:12px;">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Attendance
                </button>
            </div>
        </form>
    </div>

    <!-- Attendance Summary -->
    <div class="card">
        <div class="card-header"><h3>Attendance Summary</h3></div>
        <div class="table-container">
            <table>
                <thead>
                    <tr><th>Roll No</th><th>Student</th><th>Present</th><th>Total</th><th>%</th></tr>
                </thead>
                <tbody>
                <?php
                $has_rows = false;
                if ($att_summary) while ($r = $att_summary->fetch_assoc()):
                    $has_rows = true;
                    $pct = $r['pct'] ?? 0;
                    $cls = $pct >= 75 ? 'badge-success' : ($pct >= 50 ? 'badge-warning' : 'badge-danger');
                ?>
                <tr>
                    <td><?= htmlspecialchars($r['roll_no']) ?></td>
                    <td><?= htmlspecialchars($r['name']) ?></td>
                    <td><?= $r['present_cnt'] ?></td>
                    <td><?= $r['total_cls'] ?></td>
                    <td><span class="badge <?= $cls ?>"><?= $pct ?>%</span></td>
                </tr>
                <?php endwhile; ?>
                <?php if (!$has_rows): ?>
                <tr>
                    <td colspan="5" style="text-align:center;color:#999;padding:20px;">
                        No records yet.
                    </td>
                </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<?php elseif ($sel_course): ?>
<div class="alert alert-warning">
    <i class="fas fa-info-circle"></i> No students enrolled in this course.
</div>
<?php endif; ?>

<?php endif; ?>

<script>
function setAll(val) {
    document.querySelectorAll('.att-select').forEach(s => s.value = val);
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>