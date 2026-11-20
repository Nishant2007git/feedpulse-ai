<?php
// ============================================================
// admin/dashboard.php — Executive Command Center
// ============================================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAuth(['admin', 'super_admin']);

$pdo = getDB();

// 1. High-level KPI Metrics
$totalFeedback = (int)$pdo->query('SELECT COUNT(*) FROM feedback')->fetchColumn();
$pendingMod = (int)$pdo->query("SELECT COUNT(*) FROM feedback WHERE moderation_status = 'pending'")->fetchColumn();
$openComplaints = (int)$pdo->query("SELECT COUNT(*) FROM complaints WHERE status IN ('open','in_progress','escalated')")->fetchColumn();
$breachedComplaints = (int)$pdo->query("SELECT COUNT(*) FROM complaints WHERE sla_breached = 1 AND status != 'resolved'")->fetchColumn();

// 2. Net Sentiment calculation
$posCount = (int)$pdo->query("SELECT COUNT(*) FROM feedback WHERE ai_sentiment = 'positive'")->fetchColumn();
$negCount = (int)$pdo->query("SELECT COUNT(*) FROM feedback WHERE ai_sentiment = 'negative'")->fetchColumn();
$sentimentScore = ($totalFeedback > 0) ? round((($posCount - $negCount) / $totalFeedback) * 100) : 74;

// 3. Urgent / Escalated tickets
$escStmt = $pdo->query(
    'SELECT c.*, d.name as dept_name, u.name as student_name, staff.name as staff_name
     FROM complaints c
     JOIN departments d ON c.department_id = d.id
     JOIN users u ON c.student_id = u.id
     LEFT JOIN users staff ON c.assigned_to = staff.id
     WHERE c.status = "escalated" OR c.sla_breached = 1
     ORDER BY c.sla_breached DESC, c.created_at ASC
     LIMIT 5'
);
$urgentTickets = $escStmt->fetchAll();

$pageTitle = 'Command Center — ' . APP_NAME;
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
  <div>
    <h2 class="fw-bold mb-1">Campus Command Center 🏛️</h2>
    <p class="text-secondary small mb-0">Executive oversight, AI sentiment monitoring, and SLA compliance enforcement</p>
  </div>
  <div class="d-flex gap-2">
    <a href="<?= APP_URL ?>/admin/feedback-moderation.php" class="fp-btn fp-btn-glass position-relative">
      <i class="bi bi-funnel"></i> Moderation Queue
      <?php if ($pendingMod > 0): ?>
        <span class="badge bg-warning text-dark ms-1"><?= $pendingMod ?></span>
      <?php endif; ?>
    </a>
    <a href="<?= APP_URL ?>/admin/complaints.php" class="fp-btn fp-btn-primary">
      <i class="bi bi-ticket-detailed"></i> Triage All Tickets
    </a>
  </div>
</div>

<!-- Bento KPI Cards -->
<div class="row g-3 mb-4">
  <div class="col-12 col-sm-6 col-lg-3">
    <div class="fp-glass-card p-3">
      <div class="d-flex justify-content-between align-items-start mb-2">
        <span class="text-secondary small">Campus Sentiment Index</span>
        <span class="p-2 rounded-circle" style="background: rgba(16, 185, 129, 0.15); color: #34D399;">
          <i class="bi bi-emoji-smile"></i>
        </span>
      </div>
      <div class="h3 fw-bold mb-0 text-success">+<?= $sentimentScore ?>%</div>
      <small class="text-muted"><?= $posCount ?> positive vs <?= $negCount ?> negative</small>
    </div>
  </div>

  <div class="col-12 col-sm-6 col-lg-3">
    <div class="fp-glass-card p-3">
      <div class="d-flex justify-content-between align-items-start mb-2">
        <span class="text-secondary small">Active Complaints</span>
        <span class="p-2 rounded-circle" style="background: rgba(6, 182, 212, 0.15); color: #06B6D4;">
          <i class="bi bi-hourglass-split"></i>
        </span>
      </div>
      <div class="h3 fw-bold mb-0 text-info"><?= $openComplaints ?></div>
      <small class="text-muted">Tickets in investigation</small>
    </div>
  </div>

  <div class="col-12 col-sm-6 col-lg-3">
    <div class="fp-glass-card p-3">
      <div class="d-flex justify-content-between align-items-start mb-2">
        <span class="text-secondary small">SLA Breaches</span>
        <span class="p-2 rounded-circle" style="background: rgba(220, 38, 38, 0.15); color: #F87171;">
          <i class="bi bi-lightning-charge-fill"></i>
        </span>
      </div>
      <div class="h3 fw-bold mb-0 text-danger"><?= $breachedComplaints ?></div>
      <small class="text-muted">Target turnaround missed</small>
    </div>
  </div>

  <div class="col-12 col-sm-6 col-lg-3">
    <div class="fp-glass-card p-3">
      <div class="d-flex justify-content-between align-items-start mb-2">
        <span class="text-secondary small">Pending Moderation</span>
        <span class="p-2 rounded-circle" style="background: rgba(234, 179, 8, 0.15); color: #FBBF24;">
          <i class="bi bi-shield-exclamation"></i>
        </span>
      </div>
      <div class="h3 fw-bold mb-0 text-warning"><?= $pendingMod ?></div>
      <small class="text-muted">Awaiting review</small>
    </div>
  </div>
</div>

<!-- Charts Row -->
<div class="row g-4 mb-4">
  <div class="col-12 col-lg-6">
    <div class="fp-glass-card p-4 h-100">
      <h5 class="fw-bold mb-3"><i class="bi bi-pie-chart text-accent me-2"></i>Campus AI Sentiment Triage</h5>
      <div style="height: 260px; position: relative;">
        <canvas id="sentimentChart"></canvas>
      </div>
    </div>
  </div>

  <div class="col-12 col-lg-6">
    <div class="fp-glass-card p-4 h-100">
      <h5 class="fw-bold mb-3"><i class="bi bi-bar-chart-line text-info me-2"></i>Complaint Volume by Department</h5>
      <div style="height: 260px; position: relative;">
        <canvas id="deptChart"></canvas>
      </div>
    </div>
  </div>
</div>

<!-- Urgent Escalations Table -->
<div class="fp-glass-card p-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="fw-bold mb-0 text-danger"><i class="bi bi-exclamation-octagon-fill me-2"></i>SLA Breaches & Critical Escalations</h5>
    <a href="<?= APP_URL ?>/admin/complaints.php" class="small text-info text-decoration-none">Manage All Tickets &rarr;</a>
  </div>

  <?php if (empty($urgentTickets)): ?>
    <div class="text-center py-4 text-secondary">
      <i class="bi bi-shield-check fs-1 text-success mb-2 d-block"></i>
      Great news! No SLA breaches or escalated tickets currently detected.
    </div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table table-dark table-hover align-middle mb-0" style="background: transparent;">
        <thead>
          <tr class="text-secondary" style="border-bottom: 1px solid var(--fp-border-glass); font-size: 0.8rem; text-transform: uppercase;">
            <th>Ref</th>
            <th>Title</th>
            <th>Department</th>
            <th>Student</th>
            <th>Assigned Staff</th>
            <th>Status</th>
            <th>SLA Deadline</th>
            <th class="text-end">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($urgentTickets as $u): ?>
            <tr style="border-bottom: 1px solid var(--fp-border-glass);">
              <td><code class="text-danger fw-bold"><?= escHtml($u['reference_number']) ?></code></td>
              <td class="fw-semibold text-light"><?= escHtml($u['title']) ?></td>
              <td class="text-secondary small"><?= escHtml($u['dept_name']) ?></td>
              <td class="text-secondary small"><?= escHtml($u['student_name']) ?></td>
              <td class="text-secondary small"><?= $u['staff_name'] ? escHtml($u['staff_name']) : '<span class="text-warning">Unassigned</span>' ?></td>
              <td><?= statusBadge($u['status']) ?></td>
              <td class="text-danger small fw-bold"><?= fmtDate($u['sla_deadline']) ?></td>
              <td class="text-end">
                <a href="<?= APP_URL ?>/admin/complaints.php?focus=<?= $u['id'] ?>" class="fp-btn fp-btn-danger" style="padding: 0.35rem 0.85rem; font-size: 0.8rem;">
                  Intervene &rarr;
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', async () => {
  // 1. Sentiment Doughnut
  try {
    const res = await fetch('<?= APP_URL ?>/api/analytics.php?type=sentiment_distribution');
    const sData = await res.json();
    new Chart(document.getElementById('sentimentChart'), {
      type: 'doughnut',
      data: {
        labels: sData.labels,
        datasets: [{
          data: sData.data,
          backgroundColor: ['#059669', '#64748B', '#DC2626'],
          borderColor: 'transparent',
          hoverOffset: 6
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { position: 'bottom', labels: { color: '#94A3B8', font: { family: 'Inter' } } }
        }
      }
    });
  } catch(e) {}

  // 2. Department Volume Bar
  try {
    const dRes = await fetch('<?= APP_URL ?>/api/analytics.php?type=dept_complaints');
    const dData = await dRes.json();
    new Chart(document.getElementById('deptChart'), {
      type: 'bar',
      data: {
        labels: dData.labels,
        datasets: [{
          label: 'Tickets',
          data: dData.data,
          backgroundColor: '#4F46E5',
          borderRadius: 6
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
          x: { ticks: { color: '#94A3B8' }, grid: { color: 'rgba(255,255,255,0.05)' } },
          y: { ticks: { color: '#94A3B8' }, grid: { color: 'rgba(255,255,255,0.05)' } }
        },
        plugins: {
          legend: { display: false }
        }
      }
    });
  } catch(e) {}
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
