<?php
// ============================================================
// student/submit-feedback.php — Submit Campus Feedback
// ============================================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/logger.php';

requireAuth(['student']);

$pdo = getDB();
$studentId = $_SESSION['user_id'];
$error = '';

// Load departments and categories
$depts = $pdo->query('SELECT id, name, code FROM departments WHERE is_active = 1 ORDER BY name ASC')->fetchAll();
$cats  = $pdo->query('SELECT id, name FROM feedback_categories WHERE is_active = 1 ORDER BY name ASC')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();

    $deptId      = sanitizeInt($_POST['department_id'] ?? 0);
    $catId       = sanitizeInt($_POST['category_id'] ?? 0);
    $rating      = sanitizeInt($_POST['rating'] ?? 0);
    $title       = sanitizeString($_POST['title'] ?? '', 200);
    $comment     = sanitizeString($_POST['comment'] ?? '', 3000);
    $isAnonymous = isset($_POST['is_anonymous']) ? 1 : 0;

    if ($deptId <= 0 || $catId <= 0 || $rating < 1 || $rating > 5 || empty($comment)) {
        $error = 'Please complete all required fields and provide a rating between 1 and 5 stars.';
    } else {
        // Quick rule-based AI sentiment determination on server
        $textLower = strtolower($comment);
        $sentiment = 'neutral';
        $confidence = 0.75;
        if (str_contains($textLower, 'great') || str_contains($textLower, 'good') || str_contains($textLower, 'excellent')) {
            $sentiment = 'positive';
            $confidence = 0.88;
        } elseif (str_contains($textLower, 'bad') || str_contains($textLower, 'terrible') || str_contains($textLower, 'broken') || str_contains($textLower, 'worst')) {
            $sentiment = 'negative';
            $confidence = 0.91;
        }

        // When anonymous, do NOT store student_id and do NOT tie points/logs to student
        $finalStudentId = $isAnonymous ? null : $studentId;

        $insert = $pdo->prepare(
            'INSERT INTO feedback (student_id, department_id, category_id, rating, title, comment, is_anonymous, moderation_status, ai_sentiment, ai_confidence)
             VALUES (?, ?, ?, ?, ?, ?, ?, "pending", ?, ?)'
        );
        $insert->execute([$finalStudentId, $deptId, $catId, $rating, $title, $comment, $isAnonymous, $sentiment, $confidence]);
        $feedbackId = (int)$pdo->lastInsertId();

        if (!$isAnonymous) {
            // Award engagement score (+10) only for non-anonymous submissions
            addEngagementPoints($pdo, $studentId, POINTS_FEEDBACK);

            logEvent($pdo, 'info', 'feedback', 'Feedback submitted', [
                'feedback_id'  => $feedbackId,
                'is_anonymous' => false,
                'sentiment'    => $sentiment
            ], $studentId);

            setFlash('success', 'Thank you! Your feedback has been queued for moderation and +10 civic engagement points were added to your profile.');
        } else {
            // Log purely anonymous event without student identity
            logEvent($pdo, 'info', 'feedback', 'Anonymous feedback submitted', [
                'feedback_id'  => $feedbackId,
                'is_anonymous' => true,
                'sentiment'    => $sentiment
            ], null);

            setFlash('success', 'Thank you! Your anonymous feedback has been safely submitted and queued for moderation.');
        }

        csrfRotate();
        redirect(APP_URL . '/student/dashboard.php');
    }
}

$pageTitle = 'Submit Feedback — ' . APP_NAME;
require_once __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center my-4">
  <div class="col-12 col-md-9 col-lg-7">
    <div class="fp-glass-card p-4 p-md-5">

      <div class="d-flex align-items-center gap-3 mb-4">
        <div class="p-3 rounded-circle" style="background: rgba(16, 185, 129, 0.15); color: #34D399;">
          <i class="bi bi-chat-heart-fill fs-2"></i>
        </div>
        <div>
          <h3 class="fw-bold mb-1">Share Campus Feedback</h3>
          <p class="text-secondary small mb-0">Help improve university services, teaching, facilities, and administration</p>
        </div>
      </div>

      <?php if (!empty($error)): ?>
        <div class="fp-alert fp-alert--danger">
          <i class="bi bi-exclamation-triangle-fill"></i>
          <div><?= escHtml($error) ?></div>
        </div>
      <?php endif; ?>

      <form method="POST" action="<?= APP_URL ?>/student/submit-feedback.php">
        <?= csrfField() ?>

        <!-- Department & Category Row -->
        <div class="row g-3 mb-4">
          <div class="col-12 col-md-6">
            <label class="fp-form-label" for="fb-dept">Department / Service Area *</label>
            <select id="fb-dept" name="department_id" class="fp-select" required>
              <option value="">Choose department...</option>
              <?php foreach ($depts as $d): ?>
                <option value="<?= $d['id'] ?>" <?= (isset($_POST['department_id']) && $_POST['department_id'] == $d['id']) ? 'selected' : '' ?>>
                  <?= escHtml($d['name']) ?> (<?= escHtml($d['code']) ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-12 col-md-6">
            <label class="fp-form-label" for="fb-cat">Category *</label>
            <select id="fb-cat" name="category_id" class="fp-select" required>
              <option value="">Choose category...</option>
              <?php foreach ($cats as $c): ?>
                <option value="<?= $c['id'] ?>" <?= (isset($_POST['category_id']) && $_POST['category_id'] == $c['id']) ? 'selected' : '' ?>>
                  <?= escHtml($c['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <!-- Star Rating Widget -->
        <div class="mb-4 text-center p-3 rounded" style="background: rgba(255, 255, 255, 0.03); border: 1px solid var(--fp-border-glass);">
          <label class="fp-form-label mb-2">Overall Experience Rating *</label>
          <div class="d-flex justify-content-center align-items-center gap-2">
            <div class="fp-rating-group">
              <input type="radio" id="star5" name="rating" value="5" <?= (isset($_POST['rating']) && $_POST['rating'] == 5) ? 'checked' : '' ?> required><label for="star5">★</label>
              <input type="radio" id="star4" name="rating" value="4" <?= (isset($_POST['rating']) && $_POST['rating'] == 4) ? 'checked' : '' ?>><label for="star4">★</label>
              <input type="radio" id="star3" name="rating" value="3" <?= (isset($_POST['rating']) && $_POST['rating'] == 3) ? 'checked' : '' ?>><label for="star3">★</label>
              <input type="radio" id="star2" name="rating" value="2" <?= (isset($_POST['rating']) && $_POST['rating'] == 2) ? 'checked' : '' ?>><label for="star2">★</label>
              <input type="radio" id="star1" name="rating" value="1" <?= (isset($_POST['rating']) && $_POST['rating'] == 1) ? 'checked' : '' ?>><label for="star1">★</label>
            </div>
          </div>
          <div id="selected-rating-label" class="small text-info mt-1 font-monospace"></div>
        </div>

        <!-- Title -->
        <div class="mb-3">
          <label class="fp-form-label" for="fb-title">Subject / Title (Optional)</label>
          <input type="text" id="fb-title" name="title" class="fp-input" placeholder="e.g. Lab equipment maintenance in CS Block" value="<?= escAttr($_POST['title'] ?? '') ?>">
        </div>

        <!-- Comment with Live AI Badge -->
        <div class="mb-3">
          <div class="d-flex justify-content-between align-items-center mb-1">
            <label class="fp-form-label mb-0" for="feedback-comment-input">Detailed Comments & Suggestions *</label>
            <!-- AI Sentiment Live Badge Preview -->
            <span id="ai-sentiment-preview" class="fp-badge fp-badge--ai" style="display: none;"></span>
          </div>
          <textarea id="feedback-comment-input" name="comment" class="fp-textarea" rows="4" placeholder="Describe your experience, what went well, or what could be improved..." required><?= escHtml($_POST['comment'] ?? '') ?></textarea>
        </div>

        <!-- Anonymous Toggle Card -->
        <div class="fp-glass-card p-3 mb-4 d-flex align-items-center justify-content-between">
          <div class="d-flex align-items-center gap-3">
            <i class="bi bi-incognito fs-3 text-secondary"></i>
            <div>
              <div class="fw-semibold text-light">Submit 100% Anonymously</div>
              <small class="text-secondary">Your student identity will not be linked or visible to administrators.</small>
            </div>
          </div>
          <div class="form-check form-switch m-0">
            <input class="form-check-input" type="checkbox" role="switch" id="anon-switch" name="is_anonymous" value="1" <?= isset($_POST['is_anonymous']) ? 'checked' : '' ?> style="cursor: pointer; width: 45px; height: 24px;">
          </div>
        </div>

        <!-- Submit Button -->
        <button type="submit" class="fp-btn fp-btn-primary w-100 py-2 fs-6">
          <i class="bi bi-send-fill"></i> Submit Feedback (+10 Points)
        </button>
      </form>

    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
