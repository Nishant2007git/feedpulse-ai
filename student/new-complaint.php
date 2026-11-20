<?php
// ============================================================
// student/new-complaint.php — Lodge Official Complaint Ticket
// ============================================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/notifications.php';
require_once __DIR__ . '/../includes/logger.php';

requireAuth(['student']);

$pdo = getDB();
$studentId = $_SESSION['user_id'];
$error = '';

$depts = $pdo->query('SELECT id, name, code FROM departments WHERE is_active = 1 ORDER BY name ASC')->fetchAll();
$cats  = $pdo->query('SELECT c.id, c.name, s.target_hours FROM feedback_categories c LEFT JOIN sla_targets s ON s.category_id = c.id WHERE c.is_active = 1 ORDER BY c.name ASC')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();

    $deptId      = sanitizeInt($_POST['department_id'] ?? 0);
    $catId       = sanitizeInt($_POST['category_id'] ?? 0);
    $priority    = sanitizeString($_POST['priority'] ?? 'medium');
    $title       = sanitizeString($_POST['title'] ?? '', 200);
    $description = sanitizeString($_POST['description'] ?? '', 5000);

    if (!in_array($priority, ['low', 'medium', 'high', 'critical'])) {
        $priority = 'medium';
    }

    if ($deptId <= 0 || $catId <= 0 || empty($title) || empty($description)) {
        $error = 'Please fill out all required fields marked with an asterisk (*).';
    } else {
        try {
            $pdo->beginTransaction();

            // 1. Generate unique reference FP-YYYY-NNNNNN
            $refNumber = generateReferenceNumber($pdo);

            // 2. Compute SLA Deadline
            $createdAt = date('Y-m-d H:i:s');
            $slaDeadline = computeSlaDeadline($pdo, $catId, $createdAt);

            // 3. Insert complaint
            $stmt = $pdo->prepare(
                "INSERT INTO complaints (reference_number, student_id, department_id, category_id, title, description, priority, status, sla_deadline, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 'open', ?, ?)"
            );
            $stmt->execute([$refNumber, $studentId, $deptId, $catId, $title, $description, $priority, $slaDeadline, $createdAt]);
            $complaintId = (int)$pdo->lastInsertId();

            // 4. Initial audit log entry
            $pdo->prepare(
                "INSERT INTO complaint_updates (complaint_id, updated_by, old_status, new_status, note)
                 VALUES (?, ?, 'open', 'open', 'Ticket created by student')"
            )->execute([$complaintId, $studentId]);

            // 5. Handle File Attachment
            if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['attachment'];
                $size = (int)$file['size'];
                $origName = $file['name'];
                $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
                $mime = mime_content_type($file['tmp_name']);

                // Block dangerous scripts and enforce allowlists
                $disallowedExts = ['php', 'phtml', 'phar', 'exe', 'bat', 'cmd', 'sh', 'pl', 'py', 'js', 'html', 'htm', 'asp', 'aspx', 'jsp', 'cgi'];
                if (in_array($ext, $disallowedExts, true)) {
                    throw new RuntimeException('Uploaded file type is disallowed for security reasons.');
                }

                if ($size <= (UPLOAD_MAX_MB * 1024 * 1024) 
                    && in_array($mime, UPLOAD_ALLOWED_TYPES, true) 
                    && in_array($ext, UPLOAD_ALLOWED_EXTS, true)) {
                    
                    $storedName = bin2hex(random_bytes(16)) . '.' . $ext;
                    $dest = UPLOAD_DIR . $storedName;

                    // Safely store base64 in database for serverless persistence
                    $fileBlob = null;
                    if (file_exists($file['tmp_name']) && is_readable($file['tmp_name'])) {
                        $fileBlob = base64_encode(file_get_contents($file['tmp_name']));
                    }

                    // Optional local write for development
                    @move_uploaded_file($file['tmp_name'], $dest);

                    $pdo->prepare(
                        "INSERT INTO feedback_attachments (complaint_id, uploaded_by, original_name, stored_name, mime_type, size_bytes, file_blob)
                         VALUES (?, ?, ?, ?, ?, ?, ?)"
                    )->execute([$complaintId, $studentId, sanitizeString($origName), $storedName, $mime, $size, $fileBlob]);
                }
            }

            // 6. Award Engagement Points (+15)
            addEngagementPoints($pdo, $studentId, POINTS_COMPLAINT);

            // 7. System notification to student
            createNotification(
                $pdo, $studentId,
                'Complaint Logged: ' . $refNumber,
                "Your ticket has been officially registered with an SLA deadline of " . fmtDate($slaDeadline) . ".",
                'complaint',
                $complaintId
            );

            logEvent($pdo, 'info', 'complaint', 'New complaint ticket created', [
                'complaint_id' => $complaintId,
                'reference'    => $refNumber,
                'sla_deadline' => $slaDeadline
            ], $studentId);

            $pdo->commit();
            csrfRotate();

            setFlash('success', "Complaint registered successfully! Reference Number: <strong>{$refNumber}</strong>. SLA resolution timer active.");
            redirect(APP_URL . '/student/complaint-details.php?id=' . $complaintId);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = 'Failed to submit complaint: ' . $e->getMessage();
            error_log('[FeedPulse Complaint Error] ' . $e->getMessage());
        }
    }
}

$pageTitle = 'Log Complaint — ' . APP_NAME;
require_once __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center my-4">
  <div class="col-12 col-md-10 col-lg-8">
    <div class="fp-glass-card p-4 p-md-5">

      <div class="d-flex align-items-center gap-3 mb-4">
        <div class="p-3 rounded-circle" style="background: rgba(6, 182, 212, 0.15); color: #06B6D4;">
          <i class="bi bi-exclamation-octagon-fill fs-2"></i>
        </div>
        <div>
          <h3 class="fw-bold mb-1">File Official Complaint Ticket</h3>
          <p class="text-secondary small mb-0">Backed by campus Service Level Agreements (SLA) with automated escalation</p>
        </div>
      </div>

      <?php if (!empty($error)): ?>
        <div class="fp-alert fp-alert--danger">
          <i class="bi bi-exclamation-triangle-fill"></i>
          <div><?= escHtml($error) ?></div>
        </div>
      <?php endif; ?>

      <form method="POST" action="<?= APP_URL ?>/student/new-complaint.php" enctype="multipart/form-data">
        <?= csrfField() ?>

        <div class="row g-3 mb-3">
          <div class="col-12 col-md-6">
            <label class="fp-form-label" for="cmp-dept">Target Department *</label>
            <select id="cmp-dept" name="department_id" class="fp-select" required>
              <option value="">Choose department...</option>
              <?php foreach ($depts as $d): ?>
                <option value="<?= $d['id'] ?>" <?= (isset($_POST['department_id']) && $_POST['department_id'] == $d['id']) ? 'selected' : '' ?>>
                  <?= escHtml($d['name']) ?> (<?= escHtml($d['code']) ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-12 col-md-6">
            <label class="fp-form-label" for="cmp-cat">Issue Category (Sets SLA Target) *</label>
            <select id="cmp-cat" name="category_id" class="fp-select" required>
              <option value="">Choose category...</option>
              <?php foreach ($cats as $c): ?>
                <option value="<?= $c['id'] ?>" <?= (isset($_POST['category_id']) && $_POST['category_id'] == $c['id']) ? 'selected' : '' ?>>
                  <?= escHtml($c['name']) ?> (<?= $c['target_hours'] ? $c['target_hours'] . 'h SLA' : '72h SLA' ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="row g-3 mb-3">
          <div class="col-12 col-md-8">
            <label class="fp-form-label" for="cmp-title">Complaint Summary / Title *</label>
            <input type="text" id="cmp-title" name="title" class="fp-input" placeholder="e.g. Unusable Wi-Fi connectivity in Engineering Block 2" required value="<?= escAttr($_POST['title'] ?? '') ?>">
          </div>
          <div class="col-12 col-md-4">
            <label class="fp-form-label" for="cmp-priority">Urgency Level *</label>
            <select id="cmp-priority" name="priority" class="fp-select" required>
              <option value="low">Low (Minor inconvenience)</option>
              <option value="medium" selected>Medium (Standard request)</option>
              <option value="high">High (Disrupts learning/work)</option>
              <option value="critical">Critical (Safety / Emergency)</option>
            </select>
          </div>
        </div>

        <div class="mb-3">
          <label class="fp-form-label" for="cmp-desc">Detailed Description of Problem *</label>
          <textarea id="cmp-desc" name="description" class="fp-textarea" rows="5" placeholder="Specify dates, exact campus locations, faculty involved (if applicable), and steps already taken..." required><?= escHtml($_POST['description'] ?? '') ?></textarea>
        </div>

        <!-- Attachment upload -->
        <div class="mb-4">
          <label class="fp-form-label" for="cmp-file">Evidence / Attachment (Optional)</label>
          <input type="file" id="cmp-file" name="attachment" class="fp-input" accept=".jpg,.jpeg,.png,.webp,.pdf,.txt">
          <small class="text-secondary d-block mt-1">Allowed formats: PNG, JPG, PDF, TXT (Max 5MB)</small>
        </div>

        <button type="submit" class="fp-btn fp-btn-primary w-100 py-2 fs-6">
          <i class="bi bi-shield-fill-check"></i> Register Ticket & Start SLA Timer (+15 Points)
        </button>
      </form>

    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
