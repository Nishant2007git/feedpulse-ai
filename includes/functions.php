<?php
// ============================================================
// includes/functions.php — Core helpers
// ============================================================

// ── CSRF ──────────────────────────────────────────────────
function generateCsrfToken(): string {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfRotate(): string {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken(string $token): bool {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['csrf_token']) || empty($token)) return false;
    return hash_equals($_SESSION['csrf_token'], $token);
}

function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(generateCsrfToken(), ENT_QUOTES, 'UTF-8') . '">';
}

function csrfCheck(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
            http_response_code(403);
            die(json_encode(['error' => 'Invalid or expired security token. Please refresh and try again.']));
        }
    }
}

// ── Sanitization & Output ─────────────────────────────────
function sanitizeString(mixed $input, int $maxLength = 255): string {
    $str = (string) $input;
    if (!mb_check_encoding($str, 'UTF-8')) {
        $str = mb_convert_encoding($str, 'UTF-8', 'UTF-8');
    }
    // Strip dangerous control characters (preserve normal whitespace & newlines)
    $str = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $str);
    return mb_substr(trim($str), 0, $maxLength, 'UTF-8');
}

function sanitizeInt(mixed $input): int {
    return (int) $input;
}

function escHtml(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function escAttr(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

// ── Pagination ────────────────────────────────────────────
function paginate(PDO $pdo, string $countSql, string $dataSql, array $params, int $page = 1): array {
    $perPage = (int) ROWS_PER_PAGE;
    $page    = max(1, (int) $page);
    $offset  = ($page - 1) * $perPage;

    $stmt = $pdo->prepare($countSql);
    $stmt->execute($params);
    $total = (int) $stmt->fetchColumn();

    // Securely bind parameters and append parameterized LIMIT/OFFSET
    $stmt = $pdo->prepare($dataSql . " LIMIT ? OFFSET ?");
    $paramIndex = 1;
    foreach ($params as $paramValue) {
        $stmt->bindValue($paramIndex++, $paramValue);
    }
    $stmt->bindValue($paramIndex++, $perPage, PDO::PARAM_INT);
    $stmt->bindValue($paramIndex++, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();

    $lastPage = max(1, (int) ceil($total / $perPage));

    return [
        'rows'      => $rows,
        'total'     => $total,
        'page'      => $page,
        'per_page'  => $perPage,
        'last_page' => $lastPage,
    ];
}

function renderPagination(array $paged, string $baseUrl): string {
    $lastPage = $paged['last_page'];
    $currentPage = $paged['page'];
    if ($lastPage <= 1) return '';

    $sep = str_contains($baseUrl, '?') ? '&' : '?';
    $html = '<nav aria-label="Pagination" class="fp-pagination"><ul class="pagination justify-content-center">';

    // Previous Button
    if ($currentPage > 1) {
        $prev = $currentPage - 1;
        $html .= "<li class=\"page-item\"><a class=\"page-link\" href=\"{$baseUrl}{$sep}page={$prev}\">&laquo;</a></li>";
    } else {
        $html .= "<li class=\"page-item disabled\"><span class=\"page-link\">&laquo;</span></li>";
    }

    // Windowed page links
    $range = 2; // Show 2 pages on either side of current page
    $start = max(1, $currentPage - $range);
    $end   = min($lastPage, $currentPage + $range);

    if ($start > 1) {
        $html .= "<li class=\"page-item\"><a class=\"page-link\" href=\"{$baseUrl}{$sep}page=1\">1</a></li>";
        if ($start > 2) {
            $html .= '<li class="page-item disabled"><span class="page-link">…</span></li>';
        }
    }

    for ($i = $start; $i <= $end; $i++) {
        $active = ($i === $currentPage) ? ' active' : '';
        $html .= "<li class=\"page-item{$active}\"><a class=\"page-link\" href=\"{$baseUrl}{$sep}page={$i}\">{$i}</a></li>";
    }

    if ($end < $lastPage) {
        if ($end < $lastPage - 1) {
            $html .= '<li class="page-item disabled"><span class="page-link">…</span></li>';
        }
        $html .= "<li class=\"page-item\"><a class=\"page-link\" href=\"{$baseUrl}{$sep}page={$lastPage}\">{$lastPage}</a></li>";
    }

    // Next Button
    if ($currentPage < $lastPage) {
        $next = $currentPage + 1;
        $html .= "<li class=\"page-item\"><a class=\"page-link\" href=\"{$baseUrl}{$sep}page={$next}\">&raquo;</a></li>";
    } else {
        $html .= "<li class=\"page-item disabled\"><span class=\"page-link\">&raquo;</span></li>";
    }

    return $html . '</ul></nav>';
}

// ── Reference Number ──────────────────────────────────────
function generateReferenceNumber(PDO $pdo): string {
    $year = date('Y');
    // Concurrency-safe unique reference generation with cryptographic entropy
    for ($attempt = 0; $attempt < 10; $attempt++) {
        $randomSuffix = strtoupper(bin2hex(random_bytes(3))); // 6 hex chars e.g. 7A8B9C
        $ref = 'FP-' . $year . '-' . $randomSuffix;
        $stmt = $pdo->prepare('SELECT id FROM complaints WHERE reference_number = ? LIMIT 1');
        $stmt->execute([$ref]);
        if (!$stmt->fetch()) {
            return $ref;
        }
    }
    return 'FP-' . $year . '-' . strtoupper(substr(uniqid(), -6));
}

// ── SLA ───────────────────────────────────────────────────
function computeSlaDeadline(PDO $pdo, int $categoryId, string $createdAt): string {
    $stmt = $pdo->prepare('SELECT target_hours FROM sla_targets WHERE category_id = ?');
    $stmt->execute([$categoryId]);
    $row   = $stmt->fetch();
    $hours = $row ? (int) $row['target_hours'] : SLA_DEFAULT_HOURS;
    $dt    = new DateTime($createdAt);
    $dt->modify("+{$hours} hours");
    return $dt->format('Y-m-d H:i:s');
}

function getSlaStatus(?string $deadline, int $breached = 0): string {
    if ($breached) return 'breach';
    if (!$deadline) return 'ok';
    $now       = new DateTime();
    $sla       = new DateTime($deadline);
    $remaining = $now->diff($sla);
    if ($now >= $sla) return 'breach';
    $hoursLeft = ($remaining->days * 24) + $remaining->h;
    if ($hoursLeft <= 8) return 'warning';
    return 'ok';
}

function slaRemainingText(?string $deadline, int $breached = 0): string {
    if ($breached) return 'SLA Breached';
    if (!$deadline) return 'No Deadline';
    $now  = new DateTime();
    $sla  = new DateTime($deadline);
    if ($now >= $sla) return 'SLA Breached';
    $diff = $now->diff($sla);
    if ($diff->days > 0) return $diff->days . 'd ' . $diff->h . 'h remaining';
    if ($diff->h > 0)    return $diff->h . 'h ' . $diff->i . 'm remaining';
    return $diff->i . 'm remaining';
}

function slaPct(?string $createdAt, ?string $deadline): int {
    if (!$createdAt || !$deadline) return 0;
    $created = new DateTime($createdAt);
    $sla     = new DateTime($deadline);
    $now     = new DateTime();
    $total   = $sla->getTimestamp() - $created->getTimestamp();
    if ($total <= 0) return 100;
    $elapsed = $now->getTimestamp() - $created->getTimestamp();
    return (int) min(100, max(0, round($elapsed / $total * 100)));
}

// ── Engagement ────────────────────────────────────────────
function addEngagementPoints(PDO $pdo, int $studentId, int $points): void {
    if ($studentId <= 0 || $points <= 0) return;

    try {
        $inTx = $pdo->inTransaction();
        if (!$inTx) $pdo->beginTransaction();

        $pdo->prepare(
            "INSERT INTO engagement_scores (student_id, score, tier)
             VALUES (?, ?, 'bronze')
             ON DUPLICATE KEY UPDATE score = score + ?"
        )->execute([$studentId, $points, $points]);

        // Update tier
        $stmt  = $pdo->prepare('SELECT score FROM engagement_scores WHERE student_id = ?');
        $stmt->execute([$studentId]);
        $score = (int) ($stmt->fetchColumn() ?: 0);
        $tier  = 'bronze';
        if ($score >= TIER_PLATINUM) $tier = 'platinum';
        elseif ($score >= TIER_GOLD)    $tier = 'gold';
        elseif ($score >= TIER_SILVER)  $tier = 'silver';

        $pdo->prepare('UPDATE engagement_scores SET tier = ? WHERE student_id = ?')
            ->execute([$tier, $studentId]);

        if (!$inTx) $pdo->commit();
    } catch (Throwable $e) {
        if (isset($inTx) && !$inTx && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('[FeedPulse addEngagementPoints error] ' . $e->getMessage());
    }
}

// ── Dates & Formatting ────────────────────────────────────
function timeAgo(string $datetime): string {
    $now  = new DateTime();
    $past = new DateTime($datetime);
    $diff = $now->diff($past);
    if ($diff->y > 0) return $diff->y . ' year' . ($diff->y > 1 ? 's' : '') . ' ago';
    if ($diff->m > 0) return $diff->m . ' month' . ($diff->m > 1 ? 's' : '') . ' ago';
    if ($diff->d > 0) return $diff->d . ' day'   . ($diff->d > 1 ? 's' : '') . ' ago';
    if ($diff->h > 0) return $diff->h . ' hour'  . ($diff->h > 1 ? 's' : '') . ' ago';
    if ($diff->i > 0) return $diff->i . ' min'   . ($diff->i > 1 ? 's' : '') . ' ago';
    return 'just now';
}

function fmtDate(string $dt, string $format = 'd M Y, H:i'): string {
    return (new DateTime($dt))->format($format);
}

// ── Status / Priority Helpers ─────────────────────────────
function statusBadge(string $status): string {
    $map = [
        'open'        => 'fp-badge--open',
        'in_progress' => 'fp-badge--warning',
        'escalated'   => 'fp-badge--breach',
        'resolved'    => 'fp-badge--resolved',
        'closed'      => 'fp-badge--muted',
        'reopened'    => 'fp-badge--open',
        'pending'     => 'fp-badge--warning',
        'approved'    => 'fp-badge--resolved',
        'rejected'    => 'fp-badge--breach',
    ];
    $cls   = $map[$status] ?? 'fp-badge--muted';
    $label = ucfirst(str_replace('_', ' ', $status));
    return "<span class=\"fp-badge {$cls}\">{$label}</span>";
}

function priorityBadge(string $priority): string {
    $map = [
        'low'      => 'fp-badge--muted',
        'medium'   => 'fp-badge--open',
        'high'     => 'fp-badge--warning',
        'critical' => 'fp-badge--breach',
    ];
    $cls = $map[$priority] ?? 'fp-badge--muted';
    return "<span class=\"fp-badge {$cls}\">" . ucfirst($priority) . "</span>";
}

function sentimentBadge(?string $sentiment): string {
    if (!$sentiment) return '';
    $map = [
        'positive' => ['fp-badge--resolved', '😊'],
        'neutral'  => ['fp-badge--muted',    '😐'],
        'negative' => ['fp-badge--breach',   '😞'],
    ];
    [$cls, $icon] = $map[$sentiment] ?? ['fp-badge--muted', ''];
    return "<span class=\"fp-badge fp-badge--ai {$cls}\">{$icon} " . ucfirst($sentiment) . "</span>";
}

// ── Star Rating HTML ──────────────────────────────────────
function starRating(int $rating, int $max = 5): string {
    $html = '<span class="fp-stars" aria-label="' . $rating . ' out of ' . $max . '">';
    for ($i = 1; $i <= $max; $i++) {
        $filled = $i <= $rating ? 'filled' : '';
        $html  .= "<span class=\"fp-star {$filled}\">★</span>";
    }
    return $html . '</span>';
}

// ── Flash Messages ────────────────────────────────────────
function setFlash(string $type, string $message): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}

function renderFlash(): string {
    $flash = getFlash();
    if (!$flash) return '';
    $rawType = (string)($flash['type'] ?? 'info');
    $type = in_array($rawType, ['success', 'danger', 'warning', 'info'], true) ? $rawType : 'info';
    $icon = match($type) {
        'success' => '✓',
        'danger'  => '✗',
        'warning' => '⚠',
        default   => 'ℹ',
    };
    return '<div class="fp-alert fp-alert--' . $type . '" role="alert">'
         . '<span>' . $icon . '</span>'
         . ' ' . escHtml((string)($flash['message'] ?? ''))
         . '</div>';
}

// ── Redirect helpers ──────────────────────────────────────
function redirect(string $url): never {
    header('Location: ' . $url);
    exit;
}

function jsonResponse(array $data, int $code = 200): never {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}
