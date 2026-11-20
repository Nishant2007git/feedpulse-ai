<?php
// ============================================================
// student/dashboard.php — Student Command Center
// ============================================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAuth(['student']);

$pdo = getDB();
$studentId = $_SESSION['user_id'];

// 1. Fetch Engagement Score & Tier
$engStmt = $pdo->prepare('SELECT score, tier FROM engagement_scores WHERE student_id = ?');
$engStmt->execute([$studentId]);
$engagement = $engStmt->fetch() ?: ['score' => 0, 'tier' => 'bronze'];

// 2. Metrics
$cStmt = $pdo->prepare('SELECT COUNT(*) FROM complaints WHERE student_id = ?');
$cStmt->execute([$studentId]);
$totalComplaints = (int)$cStmt->fetchColumn();

$openStmt = $pdo->prepare('SELECT COUNT(*) FROM complaints WHERE student_id = ? AND status IN ("open","in_progress","escalated")');
$openStmt->execute([$studentId]);
$activeComplaints = (int)$openStmt->fetchColumn();

$fbStmt = $pdo->prepare('SELECT COUNT(*) FROM feedback WHERE student_id = ?');
$fbStmt->execute([$studentId]);
$totalFeedback = (int)$fbStmt->fetchColumn();

// 3. Recent Complaints
$recStmt = $pdo->prepare(
    'SELECT c.*, d.name as dept_name, cat.name as category_name 
     FROM complaints c
     JOIN departments d ON c.department_id = d.id
     JOIN feedback_categories cat ON c.category_id = cat.id
     WHERE c.student_id = ?
     ORDER BY c.created_at DESC LIMIT 5'
);
$recStmt->execute([$studentId]);
$recentComplaints = $recStmt->fetchAll();

$pageTitle = 'Student Portal — ' . APP_NAME;
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Student Welcome & Tier Header -->
<div class="row g-4 align-items-center mb-4">
  <div class="col-12 col-md-8">
    <h2 class="fw-bold mb-1">Welcome back, <?= escHtml($_SESSION['name']) ?> 👋</h2>
    <p class="text-secondary small mb-0">Track your submitted feedback, monitor complaint SLA timers, and earn civic engagement points.</p>
  </div>
  <div class="col-12 col-md-4 text-md-end">
    <div class="fp-glass-card p-3 d-inline-flex align-items-center gap-3">
      <div>
        <div class="text-secondary small">Civic Engagement</div>
        <div class="h5 fw-bold mb-0 text-light"><?= number_format($engagement['score']) ?> pts</div>
      </div>
      <span class="fp-tier fp-tier--<?= $engagement['tier'] ?>">
        <i class="bi bi-award-fill"></i> <?= ucfirst($engagement['tier']) ?>
      </span>
    </div>
  </div>
</div>

<!-- Bento Metric Cards -->
<div class="row g-3 mb-4">
  <div class="col-12 col-sm-6 col-lg-3">
    <div class="fp-glass-card p-3">
      <div class="d-flex justify-content-between align-items-start mb-2">
        <span class="text-secondary small">Active Tickets</span>
        <span class="p-2 rounded-circle" style="background: rgba(6, 182, 212, 0.15); color: #06B6D4;">
          <i class="bi bi-hourglass-split"></i>
        </span>
      </div>
      <div class="h3 fw-bold mb-0 text-info"><?= $activeComplaints ?></div>
      <small class="text-muted">In resolution pipeline</small>
    </div>
  </div>

  <div class="col-12 col-sm-6 col-lg-3">
    <div class="fp-glass-card p-3">
      <div class="d-flex justify-content-between align-items-start mb-2">
        <span class="text-secondary small">Total Complaints</span>
        <span class="p-2 rounded-circle" style="background: rgba(79, 70, 229, 0.15); color: #818CF8;">
          <i class="bi bi-card-checklist"></i>
        </span>
      </div>
      <div class="h3 fw-bold mb-0 text-light"><?= $totalComplaints ?></div>
      <small class="text-muted">Lifetime logged</small>
    </div>
  </div>

  <div class="col-12 col-sm-6 col-lg-3">
    <div class="fp-glass-card p-3">
      <div class="d-flex justify-content-between align-items-start mb-2">
        <span class="text-secondary small">Feedback Shared</span>
        <span class="p-2 rounded-circle" style="background: rgba(16, 185, 129, 0.15); color: #34D399;">
          <i class="bi bi-chat-heart"></i>
        </span>
      </div>
      <div class="h3 fw-bold mb-0 text-success"><?= $totalFeedback ?></div>
      <small class="text-muted">Ratings & reviews</small>
    </div>
  </div>

  <div class="col-12 col-sm-6 col-lg-3">
    <div class="fp-glass-card p-3 d-flex flex-column justify-content-center">
      <a href="<?= APP_URL ?>/student/new-complaint.php" class="fp-btn fp-btn-primary mb-2 py-2">
        <i class="bi bi-plus-circle"></i> Log New Complaint
      </a>
      <a href="<?= APP_URL ?>/student/submit-feedback.php" class="fp-btn fp-btn-glass py-2">
        <i class="bi bi-star"></i> Submit Feedback
      </a>
    </div>
  </div>
</div>

<!-- Recent Complaints Section -->
<div class="row">
  <div class="col-12">
    <div class="fp-glass-card p-4">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="fw-bold mb-0"><i class="bi bi-ticket-detailed text-accent me-2"></i>My Recent Complaint Tickets</h5>
        <a href="<?= APP_URL ?>/student/my-complaints.php" class="small text-info text-decoration-none">View All Tickets &rarr;</a>
      </div>

      <?php if (empty($recentComplaints)): ?>
        <div class="text-center py-5">
          <i class="bi bi-inbox fs-1 text-muted mb-2 d-block"></i>
          <p class="text-secondary mb-3">You have not submitted any complaint tickets yet.</p>
          <a href="<?= APP_URL ?>/student/new-complaint.php" class="fp-btn fp-btn-accent">File Your First Complaint</a>
        </div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-dark table-hover align-middle mb-0" style="background: transparent;">
            <thead>
              <tr class="text-secondary" style="border-bottom: 1px solid var(--fp-border-glass); font-size: 0.8rem; text-transform: uppercase;">
                <th>Reference</th>
                <th>Subject</th>
                <th>Department</th>
                <th>Priority</th>
                <th>Status</th>
                <th>SLA Countdown</th>
                <th class="text-end">Action</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($recentComplaints as $c): ?>
                <?php 
                  $slaStatus = getSlaStatus($c['sla_deadline'], (int)$c['sla_breached']);
                  $slaFillClass = match($slaStatus) {
                    'breach'  => 'fp-sla-fill--breach',
                    'warning' => 'fp-sla-fill--warning',
                    default   => 'fp-sla-fill--ok'
                  };
                  $pct = slaPct($c['created_at'], $c['sla_deadline']);
                ?>
                <tr style="border-bottom: 1px solid var(--fp-border-glass);">
                  <td><code class="text-info"><?= escHtml($c['reference_number']) ?></code></td>
                  <td class="fw-semibold text-light"><?= escHtml($c['title']) ?></td>
                  <td class="text-secondary small"><?= escHtml($c['dept_name']) ?></td>
                  <td><?= priorityBadge($c['priority']) ?></td>
                  <td><?= statusBadge($c['status']) ?></td>
                  <td style="min-width: 150px;">
                    <div class="small text-muted mb-1 d-flex justify-content-between">
                      <span><?= slaRemainingText($c['sla_deadline'], (int)$c['sla_breached']) ?></span>
                      <span><?= $pct ?>%</span>
                    </div>
                    <div class="fp-sla-track">
                      <div class="fp-sla-fill <?= $slaFillClass ?>" style="width: <?= $pct ?>%;"></div>
                    </div>
                  </td>
                  <td class="text-end">
                    <a href="<?= APP_URL ?>/student/complaint-details.php?id=<?= $c['id'] ?>" class="fp-btn fp-btn-glass" style="padding: 0.35rem 0.75rem; font-size: 0.8rem;">
                      View Details
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>

    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
