<?php
// ============================================================
// admin/analytics.php — Institutional Intelligence & Analytics
// ============================================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAuth(['admin', 'super_admin']);

$pdo = getDB();

// Department Performance Matrix
$deptStatsStmt = $pdo->query(
    'SELECT 
       d.name, d.code,
       COUNT(DISTINCT c.id) as total_complaints,
       SUM(CASE WHEN c.sla_breached = 1 THEN 1 ELSE 0 END) as breached_count,
       ROUND(AVG(fb.rating), 1) as avg_rating
     FROM departments d
     LEFT JOIN complaints c ON c.department_id = d.id
     LEFT JOIN feedback fb ON fb.department_id = d.id AND fb.moderation_status = 'approved'
     GROUP BY d.id, d.name, d.code
     ORDER BY total_complaints DESC'
);
$deptStats = $deptStatsStmt->fetchAll();

$pageTitle = 'Institutional Analytics — ' . APP_NAME;
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
  <div>
    <h2 class="fw-bold mb-1"><i class="bi bi-graph-up-arrow text-accent me-2"></i>Institutional Intelligence & Analytics</h2>
    <p class="text-secondary small mb-0">Departmental accountability scores, sentiment metrics, and SLA breach trends</p>
  </div>
  <div class="d-flex gap-2">
    <a href="<?= APP_URL ?>/admin/export.php?type=feedback" class="fp-btn fp-btn-glass" title="Export Student Feedback to CSV">
      <i class="bi bi-file-earmark-text text-info me-1"></i> Feedback CSV
    </a>
    <a href="<?= APP_URL ?>/admin/export.php?type=complaints" class="fp-btn fp-btn-glass" title="Export Complaints to CSV">
      <i class="bi bi-file-earmark-spreadsheet-fill text-success me-1"></i> Complaints CSV
    </a>
  </div>
</div>

<!-- Charts Row -->
<div class="row g-4 mb-4">
  <div class="col-12 col-lg-6">
    <div class="fp-glass-card p-4 h-100">
      <h5 class="fw-bold mb-3"><i class="bi bi-shield-check text-success me-2"></i>Campus SLA Resolution Compliance</h5>
      <div style="height: 250px; position: relative;">
        <canvas id="slaChart"></canvas>
      </div>
    </div>
  </div>

  <div class="col-12 col-lg-6">
    <div class="fp-glass-card p-4 h-100">
      <h5 class="fw-bold mb-3"><i class="bi bi-pie-chart text-info me-2"></i>Feedback Sentiment Triage</h5>
      <div style="height: 250px; position: relative;">
        <canvas id="sentimentChart"></canvas>
      </div>
    </div>
  </div>
</div>

<!-- Department Performance Table -->
<div class="fp-glass-card p-4">
  <h5 class="fw-bold mb-3"><i class="bi bi-table text-accent me-2"></i>Department Performance & Accountability Matrix</h5>

  <div class="table-responsive">
    <table class="table table-dark table-hover align-middle mb-0" style="background: transparent;">
      <thead>
        <tr class="text-secondary" style="border-bottom: 1px solid var(--fp-border-glass); font-size: 0.8rem; text-transform: uppercase;">
          <th>Department</th>
          <th>Code</th>
          <th>Total Complaints</th>
          <th>SLA Breaches</th>
          <th>SLA Compliance %</th>
          <th>Avg Rating</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($deptStats as $d): ?>
          <?php 
            $tot = (int)$d['total_complaints'];
            $brk = (int)$d['breached_count'];
            $compRate = ($tot > 0) ? round((($tot - $brk) / $tot) * 100) : 100;
          ?>
          <tr style="border-bottom: 1px solid var(--fp-border-glass);">
            <td class="fw-semibold text-light"><?= escHtml($d['name']) ?></td>
            <td><code class="text-info"><?= escHtml($d['code']) ?></code></td>
            <td><?= $tot ?></td>
            <td>
              <span class="badge <?= $brk > 0 ? 'bg-danger' : 'bg-secondary' ?>"><?= $brk ?></span>
            </td>
            <td>
              <div class="d-flex align-items-center gap-2">
                <span class="small fw-bold <?= $compRate >= 90 ? 'text-success' : 'text-warning' ?>"><?= $compRate ?>%</span>
                <div class="fp-sla-track" style="width: 100px;">
                  <div class="fp-sla-fill <?= $compRate >= 90 ? 'fp-sla-fill--ok' : 'fp-sla-fill--warning' ?>" style="width: <?= $compRate ?>%;"></div>
                </div>
              </div>
            </td>
            <td>
              <?php if ($d['avg_rating']): ?>
                <span class="text-warning">★ <?= $d['avg_rating'] ?></span>
              <?php else: ?>
                <span class="text-muted small">No reviews</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', async () => {
  // SLA Compliance Chart
  try {
    const res = await fetch('<?= APP_URL ?>/api/analytics.php?type=sla_compliance');
    const data = await res.json();
    new Chart(document.getElementById('slaChart'), {
      type: 'doughnut',
      data: {
        labels: data.labels,
        datasets: [{
          data: data.data,
          backgroundColor: ['#059669', '#DC2626'],
          borderColor: 'transparent'
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

  // Sentiment Chart
  try {
    const sRes = await fetch('<?= APP_URL ?>/api/analytics.php?type=sentiment_distribution');
    const sData = await sRes.json();
    new Chart(document.getElementById('sentimentChart'), {
      type: 'pie',
      data: {
        labels: sData.labels,
        datasets: [{
          data: sData.data,
          backgroundColor: ['#059669', '#64748B', '#DC2626'],
          borderColor: 'transparent'
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
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
