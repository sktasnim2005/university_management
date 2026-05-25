<?php
// ============================================================
//  modules/grades.php
//  Professional Version
//  - Clean error handling
//  - Auto-hide alerts
//  - Grade update support
//  - Student-only grade viewing
// ============================================================

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/db.php';

$msg = $err = '';


// ── GRADING POLICY ───────────────────────────────────────────
function calcGrade(float $pct): array {

    if ($pct >= 80) return ['A+', 4.00];
    if ($pct >= 75) return ['A',  3.75];
    if ($pct >= 70) return ['A-', 3.50];
    if ($pct >= 65) return ['B+', 3.25];
    if ($pct >= 60) return ['B',  3.00];
    if ($pct >= 55) return ['B-', 2.75];
    if ($pct >= 50) return ['C+', 2.50];
    if ($pct >= 45) return ['C',  2.25];
    if ($pct >= 40) return ['D',  2.00];

    return ['F', 0.00];
}


// ── ADD / UPDATE GRADE ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isStudent()) {
        die("<div class='alert alert-danger'>⛔ Access Denied!</div>");
    }

    $enr_id  = intval($_POST['enrollment_id']);
    $marks   = floatval($_POST['marks_obtained']);
    $total   = floatval($_POST['total_marks']);
    $exam_dt = trim($_POST['exam_date']);
    $remarks = trim($_POST['remarks']);

    // Validation
    if ($marks < 0 || $total <= 0) {

        $err = "Invalid marks entered.";

    } elseif ($marks > $total) {

        $err = "Marks obtained cannot be greater than total marks.";

    } else {

        try {

            // Calculate percentage + grade
            $pct = ($marks / $total) * 100;

            [$grade, $pts] = calcGrade($pct);

            // Check existing grade
            $check = $conn->prepare(
                "SELECT grade_id
                 FROM grades
                 WHERE enrollment_id=?"
            );

            $check->bind_param("i", $enr_id);
            $check->execute();

            $result = $check->get_result();

            // UPDATE
            if ($result->num_rows > 0) {

                $stmt = $conn->prepare(
                    "UPDATE grades
                     SET marks_obtained=?,
                         total_marks=?,
                         grade_letter=?,
                         grade_points=?,
                         exam_date=?,
                         remarks=?
                     WHERE enrollment_id=?"
                );

                $stmt->bind_param(
                    "ddsdssi",
                    $marks,
                    $total,
                    $grade,
                    $pts,
                    $exam_dt,
                    $remarks,
                    $enr_id
                );

                if ($stmt->execute()) {
                    $msg = "Grade updated successfully! ($grade)";
                }

            }

            // INSERT
            else {

                $stmt = $conn->prepare(
                    "INSERT INTO grades
                    (
                        enrollment_id,
                        marks_obtained,
                        total_marks,
                        grade_letter,
                        grade_points,
                        exam_date,
                        remarks
                    )
                    VALUES (?, ?, ?, ?, ?, ?, ?)"
                );

                $stmt->bind_param(
                    "iddsdss",
                    $enr_id,
                    $marks,
                    $total,
                    $grade,
                    $pts,
                    $exam_dt,
                    $remarks
                );

                if ($stmt->execute()) {
                    $msg = "Grade saved successfully! ($grade)";
                }
            }

        } catch (mysqli_sql_exception $e) {

            switch ($e->getCode()) {

                // Duplicate
                case 1062:
                    $err = "This grade record already exists.";
                    break;

                // Required field missing
                case 1048:
                    $err = "Please fill all required fields.";
                    break;

                // Foreign key issue
                case 1452:
                    $err = "Invalid enrollment selected.";
                    break;

                // Data too long
                case 1406:
                    $err = "One or more fields contain too much text.";
                    break;

                default:
                    $err = "Unable to save grade. Please try again.";
            }

            // Save real error privately
            error_log($e->getMessage());
        }
    }
}


// ── DATA FETCH ───────────────────────────────────────────────
$my_id = intval($_SESSION['ref_id'] ?? 0);

$grade_sql = "

    SELECT g.*,
           s.first_name,
           s.last_name,
           s.roll_no,
           c.course_name,
           c.course_code

    FROM grades g

    JOIN enrollments e
        ON g.enrollment_id = e.enrollment_id

    JOIN students s
        ON e.student_id = s.student_id

    JOIN courses c
        ON e.course_id = c.course_id
";

if (isStudent()) {
    $grade_sql .= " WHERE e.student_id = $my_id";
}

$grade_sql .= " ORDER BY g.exam_date DESC";

$grades = $conn->query($grade_sql);


// ── ENROLLMENTS FOR DROPDOWN ─────────────────────────────────
$enrollments = null;

if (isAdmin() || isFaculty()) {

    $enrollments = $conn->query("

        SELECT e.enrollment_id,
               s.student_id,
               s.first_name,
               s.last_name,
               s.roll_no,
               c.course_name

        FROM enrollments e

        JOIN students s
            ON e.student_id = s.student_id

        JOIN courses c
            ON e.course_id = c.course_id

        WHERE e.status='Enrolled'

        ORDER BY s.first_name ASC
    ");
}
?>


<div class="page-header">

    <h2>
        <i class="fas fa-graduation-cap"></i>

        Grades

        <?php if (isStudent()): ?>

        <span style="font-size:0.6em;color:#999">
            — My Grades
        </span>

        <?php endif; ?>
    </h2>

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



<div style="
    display:grid;
    grid-template-columns:
    <?= (isAdmin() || isFaculty()) ? '1fr 2fr' : '1fr' ?>;
    gap:20px;
">


<!-- ADD / UPDATE FORM -->
<?php if (isAdmin() || isFaculty()): ?>

<div class="card">

    <div class="card-header">
        <h3>
            <i class="fas fa-plus"></i>
            Add / Update Grade
        </h3>
    </div>

    <form method="POST">

        <div class="form-group">

            <label>Select Student &amp; Course *</label>

            <select name="enrollment_id" required>

                <option value="">
                    -- Choose Enrollment --
                </option>

                <?php while ($e = $enrollments->fetch_assoc()): ?>

                <option value="<?= $e['enrollment_id'] ?>">

                    <?= htmlspecialchars($e['roll_no']) ?> |

                    <?= htmlspecialchars(
                        $e['first_name'] . ' ' . $e['last_name']
                    ) ?>

                    —

                    <?= htmlspecialchars($e['course_name']) ?>

                </option>

                <?php endwhile; ?>

            </select>

        </div>


        <div class="form-group">

            <label>Marks Obtained *</label>

            <input type="number"
                   name="marks_obtained"
                   step="0.01"
                   min="0"
                   required>

        </div>


        <div class="form-group">

            <label>Total Marks *</label>

            <input type="number"
                   name="total_marks"
                   step="0.01"
                   value="100"
                   required>

        </div>


        <div class="form-group">

            <label>Exam Date</label>

            <input type="date"
                   name="exam_date"
                   value="<?= date('Y-m-d') ?>">

        </div>


        <div class="form-group">

            <label>Remarks</label>

            <textarea name="remarks" rows="2"></textarea>

        </div>


        <div class="form-actions">

            <button type="submit"
                    class="btn btn-success"
                    style="width:100%">

                <i class="fas fa-save"></i>

                Save Grade

            </button>

        </div>

    </form>

</div>

<?php endif; ?>



<!-- GRADE TABLE -->
<div class="card">

    <div class="card-header">

        <h3>
            <?= isStudent() ? 'My Grade Records' : 'All Grades' ?>
        </h3>

    </div>


    <div class="table-container">

        <table>

            <thead>

                <tr>

                    <?php if (!isStudent()): ?>

                    <th>Roll No</th>
                    <th>Student</th>

                    <?php endif; ?>

                    <th>Course</th>
                    <th>Marks</th>
                    <th>Grade</th>
                    <th>GPA Pts</th>
                    <th>Date</th>

                </tr>

            </thead>


            <tbody>

            <?php
            $has_rows = false;

            while ($g = $grades->fetch_assoc()):

                $has_rows = true;

                $pct = $g['total_marks'] > 0
                    ? round(
                        ($g['marks_obtained'] / $g['total_marks']) * 100,
                        1
                    )
                    : 0;

                $cls = $g['grade_letter'] === 'F'
                    ? 'badge-danger'
                    : (
                        $g['grade_points'] >= 3.5
                        ? 'badge-success'
                        : 'badge-warning'
                    );
            ?>

            <tr>

                <?php if (!isStudent()): ?>

                <td>
                    <?= htmlspecialchars($g['roll_no']) ?>
                </td>

                <td>
                    <?= htmlspecialchars(
                        $g['first_name'] . ' ' . $g['last_name']
                    ) ?>
                </td>

                <?php endif; ?>


                <td>
                    <strong>
                        <?= htmlspecialchars($g['course_code']) ?>
                    </strong>
                </td>


                <td>
                    <?= $g['marks_obtained'] ?>
                    /
                    <?= $g['total_marks'] ?>

                    (<?= $pct ?>%)
                </td>


                <td>

                    <span class="badge <?= $cls ?>">

                        <?= htmlspecialchars($g['grade_letter']) ?>

                    </span>

                </td>


                <td>

                    <?= number_format($g['grade_points'], 2) ?>

                </td>


                <td>

                    <?= htmlspecialchars($g['exam_date']) ?>

                </td>

            </tr>

            <?php endwhile; ?>


            <?php if (!$has_rows): ?>

            <tr>

                <td colspan="7"
                    style="text-align:center;color:#999;padding:20px;">

                    No grade records found.

                </td>

            </tr>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>

</div>


<?php require_once __DIR__ . '/../includes/footer.php'; ?>