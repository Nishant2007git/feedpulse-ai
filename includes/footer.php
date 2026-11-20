    </div><!-- /.container-xl -->
  </main>

  <!-- Modern Institutional Footer -->
  <footer class="mt-auto pt-5 pb-4 position-relative" style="background: rgba(6, 13, 32, 0.95); border-top: 1px solid var(--fp-border-glass); z-index: 10;">
    <div class="container-xl">
      <div class="row g-4 mb-5">
        
        <!-- Col 1: Brand Info -->
        <div class="col-12 col-md-5">
          <div class="d-flex align-items-center gap-2 mb-3">
            <span class="fp-brand-icon" style="width: 32px; height: 32px;">
              <i class="bi bi-soundwave" style="font-size: 1.15rem;"></i>
            </span>
            <span class="fp-brand-title fw-bold fs-5 text-light">FeedPulse <span class="gradient-text">AI</span></span>
          </div>
          <p class="text-secondary small mb-3" style="max-width: 380px; line-height: 1.7;">
            Next-generation campus governance, real-time sentiment NLP classification, and automated multi-tier SLA resolution tracking for institutional accountability.
          </p>
          <div class="d-inline-flex align-items-center gap-2 px-3 py-1 rounded-pill" style="background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.25); font-size: 0.78rem;">
            <span class="pulse-dot"></span>
            <span class="text-success fw-semibold">Campus SLA Engine: Operational 99.98%</span>
          </div>
        </div>

        <!-- Col 2: Navigation Links -->
        <div class="col-6 col-md-3">
          <h6 class="text-uppercase fw-bold text-light small tracking-wider mb-3" style="letter-spacing: 0.08em;">Platform Portals</h6>
          <ul class="list-unstyled d-flex flex-column gap-2 small">
            <li><a href="<?= APP_URL ?>/student/dashboard.php" class="text-secondary text-decoration-none hover-light">Student Dashboard</a></li>
            <li><a href="<?= APP_URL ?>/student/new-complaint.php" class="text-secondary text-decoration-none hover-light">Lodge Complaint</a></li>
            <li><a href="<?= APP_URL ?>/staff/dashboard.php" class="text-secondary text-decoration-none hover-light">Staff Workbench</a></li>
            <li><a href="<?= APP_URL ?>/admin/dashboard.php" class="text-secondary text-decoration-none hover-light">Admin Command Center</a></li>
          </ul>
        </div>

        <!-- Col 3: Institutional Standards -->
        <div class="col-6 col-md-4">
          <h6 class="text-uppercase fw-bold text-light small tracking-wider mb-3" style="letter-spacing: 0.08em;">Governance & Integrity</h6>
          <ul class="list-unstyled d-flex flex-column gap-2 small">
            <li class="text-secondary"><i class="bi bi-shield-check text-info me-2"></i> Guaranteed SLA Resolution Timeframes</li>
            <li class="text-secondary"><i class="bi bi-incognito text-success me-2"></i> Cryptographic Whistleblower Anonymity</li>
            <li class="text-secondary"><i class="bi bi-cpu text-accent me-2"></i> AI Sentiment Distress Auto-Triage</li>
            <li class="text-secondary"><i class="bi bi-lock text-warning me-2"></i> Role-Based Multi-Tier Escalation Matrix</li>
          </ul>
        </div>

      </div>

      <!-- Bottom Bar -->
      <div class="pt-4 border-top border-secondary border-opacity-10 d-flex flex-column flex-md-row align-items-center justify-content-between gap-3 text-secondary small">
        <div class="d-flex align-items-center gap-2">
          <span>&copy; <?= date('Y') ?> <strong><?= APP_NAME ?></strong> v<?= APP_VERSION ?></span>
          <span>&bull;</span>
          <span>Higher Education Intelligence</span>
        </div>
        <div class="d-flex align-items-center gap-3">
          <span><i class="bi bi-shield-lock-fill text-success me-1"></i> TLS 1.3 Strict Security</span>
          <span>&bull;</span>
          <span class="text-muted">Built for Academic Excellence</span>
        </div>
      </div>
    </div>
  </footer>

  <!-- Bootstrap 5 Bundle JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <!-- Chart.js for Dashboards -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <!-- FeedPulse Core Scripts -->
  <script src="<?= APP_URL ?>/assets/js/main.js"></script>
</body>
</html>
