<?php
// ============================================================
// database/seed_mock_data.php — Realistic Mock Data Generator
// Run via CLI: php database/seed_mock_data.php
// Or from browser: http://localhost/feedpulse-ai/database/seed_mock_data.php
// ============================================================

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

if (php_sapi_name() !== 'cli' && APP_ENV !== 'development') {
    http_response_code(403);
    die('Forbidden: Mock data seeder is disabled in production.');
}

$pdo = getDB();
$isCli = (php_sapi_name() === 'cli');

function logMsg(string $msg): void {
    global $isCli;
    echo $isCli ? $msg . PHP_EOL : "<div>{$msg}</div>";
}

if (!$isCli) {
    echo '<!DOCTYPE html><html><head><title>FeedPulse AI Data Generator</title>';
    echo '<style>body{background:#090D16;color:#F8FAFC;font-family:monospace;padding:2rem;} .btn{display:inline-block;padding:8px 16px;background:#4F46E5;color:#fff;text-decoration:none;border-radius:6px;margin-top:1rem;}</style>';
    echo '</head><body>';
}

logMsg("🚀 Starting FeedPulse AI High-Fidelity Data Seeder...");

$passwordHash = password_hash('password', PASSWORD_BCRYPT);

// ── 1. Create Additional Users (Staff & Students) ───────────
$staffUsers = [
    ['Engr. Tariq Aziz',    'staff.ee@feedpulse.edu',       2, 'staff'],
    ['Mr. Kamran Javed',    'staff.facilities@feedpulse.edu', 5, 'staff'],
    ['Ms. Hina Qureshi',    'staff.itservices@feedpulse.edu', 8, 'staff'],
    ['Chef Arshad Mahmood', 'staff.cafeteria@feedpulse.edu', 9, 'staff'],
    ['Mrs. Farida Yasmin',  'staff.library@feedpulse.edu',   7, 'staff'],
];

foreach ($staffUsers as [$name, $email, $deptId, $role]) {
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    if (!$stmt->fetch()) {
        $pdo->prepare('INSERT INTO users (name, email, password_hash, role, department_id, is_active) VALUES (?, ?, ?, ?, ?, 1)')
            ->execute([$name, $email, $passwordHash, $role, $deptId]);
        logMsg("  + Added Staff: {$name} ({$email})");
    }
}

$students = [
    ['Hamza Ali',       'hamza.ali@feedpulse.edu',       1, '22-CS-014',  '+92 301 2345671', 280, 'gold'],
    ['Zainab Fatima',   'zainab.f@feedpulse.edu',        1, '22-CS-089',  '+92 302 3456782', 420, 'platinum'],
    ['Bilal Ahmed',     'bilal.ahmed@feedpulse.edu',     2, '21-EE-045',  '+92 303 4567893', 110, 'silver'],
    ['Maryam Noor',     'maryam.noor@feedpulse.edu',     4, '23-BBA-102', '+92 304 5678904', 35,  'bronze'],
    ['Usman Tariq',     'usman.tariq@feedpulse.edu',     3, '20-CE-033',  '+92 305 6789015', 190, 'gold'],
    ['Ayesha Siddiqui', 'ayesha.s@feedpulse.edu',        1, '23-CS-055',  '+92 306 7890126', 80,  'silver'],
    ['Omer Farooq',     'omer.farooq@feedpulse.edu',     8, '22-ITS-012', '+92 307 8901237', 20,  'bronze'],
    ['Sara Khan',       'sara.khan@feedpulse.edu',       6, '21-SA-008',  '+92 308 9012348', 310, 'gold'],
];

$studentIds = [4]; // Default student ID 4 is already seeded

foreach ($students as [$name, $email, $deptId, $roll, $phone, $score, $tier]) {
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $existing = $stmt->fetch();
    if (!$existing) {
        $pdo->prepare('INSERT INTO users (name, email, password_hash, role, department_id, roll_number, phone, is_active) VALUES (?, ?, ?, "student", ?, ?, ?, 1)')
            ->execute([$name, $email, $passwordHash, $deptId, $roll, $phone]);
        $sId = (int)$pdo->lastInsertId();
        $studentIds[] = $sId;

        $pdo->prepare('INSERT INTO engagement_scores (student_id, score, tier) VALUES (?, ?, ?)')
            ->execute([$sId, $score, $tier]);
        logMsg("  + Added Student: {$name} ({$score} pts - {$tier})");
    } else {
        $studentIds[] = (int)$existing['id'];
    }
}

// Ensure default student has points
$pdo->prepare("INSERT INTO engagement_scores (student_id, score, tier) VALUES (4, 160, 'gold') ON DUPLICATE KEY UPDATE score = 160, tier = 'gold'")
    ->execute();

// ── 2. Seed Rich Feedback Entries ───────────────────────────
logMsg("✨ Generating Feedback with Sentiment AI Classification...");

$feedbackPool = [
    // Positive
    [1, 1, 5, 'Exceptional AI & Data Structures Lectures', 'Dr. Ahmed Khan explains complex graph algorithms with crystal clear examples and interactive visualizations. Highly inspiring coursework!', 'positive', 0.96, 'Academic Quality', 0],
    [5, 2, 4, 'Renovated Study Lounges are Fantastic', 'The new ergonomic chairs and ambient lighting in the Central Library 2nd floor have significantly boosted my group study productivity.', 'positive', 0.91, 'Facilities', 0],
    [9, 3, 5, 'Great Fresh Juice & Salad Bar Hygiene', 'The new health bar in the cafeteria is superb. Very clean food preparation and polite catering staff.', 'positive', 0.94, 'Food Services', 0],
    [8, 4, 5, 'High-Speed Wi-Fi Upgrade in CS Labs', 'The upgraded 1Gbps fiber connectivity in the Computing Labs is blazing fast. Downloads and Docker pulls finish in seconds.', 'positive', 0.97, 'IT & Technology', 1],
    [7, 7, 5, 'Digital Research Databases Access', 'Access to IEEE Xplore and ScienceDirect via the campus portal is seamless. Very helpful for final year thesis work.', 'positive', 0.95, 'Library', 0],
    [6, 8, 4, 'Well-Organized Annual Hackathon', 'FeedPulse Hackathon was exceptionally well coordinated. Great mentors, food, and judging panel.', 'positive', 0.89, 'Extracurricular', 0],
    [10, 6, 5, 'Prompt Medical Care at Health Center', 'The on-duty doctor treated my allergic reaction within 5 minutes. Clean clinic and well-stocked pharmacy.', 'positive', 0.98, 'Health & Safety', 1],

    // Neutral
    [4, 1, 3, 'Course Material Needs Earlier Uploads', 'Lectures are decent, but lecture slides and problem sets are uploaded only the night before the tutorials.', 'neutral', 0.81, 'Academic Quality', 0],
    [9, 3, 3, 'Average Lunch Variety in Faculty Canteen', 'The food is edible and prices are fair, but the weekly rotation has been identical for the last 3 months.', 'neutral', 0.76, 'Food Services', 0],
    [1, 5, 3, 'Accounts Office Queue Management', 'Fee submission process requires standing in 3 separate queues. An online payment token kiosk would help.', 'neutral', 0.84, 'Staff & Admin', 0],
    [5, 9, 3, 'Shuttle Route 4 Punctuality', 'Morning 8:15 AM shuttle occasionally arrives 15 minutes late due to city traffic. Would appreciate live GPS tracking.', 'neutral', 0.79, 'Transportation', 1],

    // Negative
    [5, 2, 1, 'AC Malfunctioning in Engineering Block 2', 'Classroom 204 has had a broken air conditioner during 40-degree heat for over a week. Students cannot concentrate.', 'negative', 0.96, 'Facilities', 0],
    [8, 4, 1, 'Frequent Wi-Fi Dropouts in Student Hostels', 'Hostel Block C Wi-Fi disconnects every 10 minutes. Impossible to submit online quizzes or attend project meetings.', 'negative', 0.97, 'IT & Technology', 1],
    [9, 3, 2, 'Uncovered Food Trays & Hygiene Concern', 'Noticed flies around the bread tray in the student cafeteria around 1:30 PM. Needs immediate manager inspection.', 'negative', 0.92, 'Food Services', 0],
    [5, 2, 2, 'Restroom Water Leakage Ground Floor', 'Restroom near Lab 3 has continuous water seepage creating slippery floors and an unpleasant odor.', 'negative', 0.94, 'Facilities', 0],
    [1, 5, 1, 'Unresponsive Department Help Desk', 'Staff at Counter 2 were dismissive when asked for degree verification guidelines. Kept asking students to come back tomorrow.', 'negative', 0.95, 'Staff & Admin', 1],
    [5, 9, 2, 'Insufficient Parking Spaces Near Gate 3', 'Bikes and student cars are parked chaotically blocking the sidewalk because parking slots are not marked.', 'negative', 0.88, 'Transportation', 0],
];

// Check current feedback count
$fbCount = (int)$pdo->query('SELECT COUNT(*) FROM feedback')->fetchColumn();
if ($fbCount < 10) {
    foreach ($feedbackPool as $idx => [$deptId, $catId, $rating, $title, $comment, $sentiment, $confidence, $catSug, $isAnon]) {
        $studentId = $isAnon ? null : $studentIds[array_rand($studentIds)];
        $modStatus = ($idx % 5 === 0) ? 'pending' : (($idx % 7 === 0) ? 'rejected' : 'approved');
        $createdOffset = rand(1, 30);
        $createdAt = date('Y-m-d H:i:s', strtotime("-{$createdOffset} days - " . rand(1, 23) . " hours"));

        $stmt = $pdo->prepare(
            'INSERT INTO feedback (student_id, department_id, category_id, rating, title, comment, is_anonymous, moderation_status, ai_sentiment, ai_confidence, ai_category_suggestion, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$studentId, $deptId, $catId, $rating, $title, $comment, $isAnon, $modStatus, $sentiment, $confidence, $catSug, $createdAt]);
        $fbId = (int)$pdo->lastInsertId();

        // Add response for approved positive/negative feedback
        if ($modStatus === 'approved' && $idx % 3 === 0) {
            $pdo->prepare(
                'INSERT INTO feedback_responses (feedback_id, responded_by, response, created_at)
                 VALUES (?, 2, "Thank you for bringing this to our attention. Our team has reviewed your input and initiated the relevant action plan.", DATE_ADD(?, INTERVAL 4 HOUR))'
            )->execute([$fbId, $createdAt]);
        }
    }
    logMsg("  + Inserted " . count($feedbackPool) . " feedback entries with AI tags and moderation records.");
}

// ── 3. Seed Realistic Complaint Tickets ──────────────────────
logMsg("🎫 Generating Complaints with SLA Timers & Lifecycle Audit Trails...");

$complaintTemplates = [
    // [dept_id, cat_id, priority, status, title, description, days_ago, sla_hours, is_breached, assigned_staff]
    [8, 4, 'critical', 'escalated', 'Wi-Fi Core Switch Down in CS Block 3', 'All labs and research cubicles in CS Block 3 have lost internet connectivity. Final year students are unable to submit capstone code.', 4, 24, 1, 3],
    [5, 2, 'high', 'in_progress', 'Faulty Ceiling Projector in Seminar Hall B', 'The HDMI and VGA inputs on the main ceiling projector are producing heavy discoloration and flickering. Guest lecture scheduled tomorrow.', 1, 24, 0, 3],
    [9, 3, 'critical', 'resolved', 'Cafeteria Water Dispenser Filter Contamination', 'Water from the main cafeteria dispenser had a metallic taste and cloudy appearance. Students requested immediate filter replacement.', 5, 12, 0, 3],
    [5, 2, 'medium', 'open', 'Broken Door Lock in Girls Common Room', 'The main privacy latch on the Girls Common Room entrance door is detached from the frame.', 0, 24, 0, null],
    [1, 1, 'high', 'in_progress', 'Grading Discrepancy in Midterm Quiz 2', 'Several question grading marks were omitted in the online LMS portal for CS-301 Section B.', 2, 48, 0, 3],
    [7, 7, 'low', 'closed', 'Missing Reference Textbooks for Algorithm Design', 'The 4 reserved copies of Introduction to Algorithms (CLRS 4th Ed) are unaccounted for on the reserve shelf.', 10, 48, 0, 3],
    [5, 9, 'high', 'escalated', 'Campus Shuttle Bus #4 Engine Breakdown', 'Bus #4 stalled twice on the Northern route leaving 60 students stranded for 45 minutes.', 3, 48, 1, 3],
    [10, 6, 'medium', 'resolved', 'First Aid Kit Refill Needed in Mechanical Workshop', 'Bandages, antiseptic solution, and burn ointment containers in the mechanical workshop first aid kit are completely empty.', 3, 12, 0, 3],
    [5, 2, 'critical', 'escalated', 'Elevator Stuck Between 3rd and 4th Floor', 'Library elevator #2 exhibited erratic braking and stopped between floors with students inside. Power was restored but lift remains dangerous.', 2, 24, 1, 3],
    [8, 4, 'medium', 'open', 'Smart Card Turnstile Malfunction at North Gate', 'RFID reader fails to recognize student ID cards on the leftmost turnstile, causing long bottlenecks during 8:30 AM arrival.', 1, 24, 0, null],
    [9, 3, 'low', 'in_progress', 'Inadequate Seating During Peak 1 PM Lunch Hour', 'Students are eating lunch standing up due to lack of cafeteria benches after the recent batch expansion.', 2, 72, 0, 3],
    [1, 1, 'medium', 'resolved', 'LMS Portal Gateway Timeout During Assignment Upload', 'Servers threw 504 Gateway Timeout during the 11:59 PM deadline for Database Systems lab assignment.', 4, 48, 0, 3],
];

$cmpCount = (int)$pdo->query('SELECT COUNT(*) FROM complaints')->fetchColumn();
if ($cmpCount < 5) {
    foreach ($complaintTemplates as $i => [$deptId, $catId, $priority, $status, $title, $desc, $daysAgo, $slaHours, $isBreached, $assignedTo]) {
        $refNum = 'FP-2026-' . str_pad($i + 1, 6, '0', STR_PAD_LEFT);
        $sId = $studentIds[array_rand($studentIds)];
        $createdAt = date('Y-m-d H:i:s', strtotime("-{$daysAgo} days - " . rand(2, 8) . " hours"));

        // SLA deadline calculation
        $slaDeadline = date('Y-m-d H:i:s', strtotime("{$createdAt} + {$slaHours} hours"));
        $resolvedAt = ($status === 'resolved' || $status === 'closed') ? date('Y-m-d H:i:s', strtotime("{$createdAt} + " . rand(4, $slaHours - 2) . " hours")) : null;
        $resNote = ($status === 'resolved' || $status === 'closed') ? 'Issue was investigated on-site by departmental technicians. Component was repaired, tested, and verified operational.' : null;
        $escalatedAt = $isBreached ? date('Y-m-d H:i:s', strtotime("{$slaDeadline} + 1 hour")) : null;
        $escCount = $isBreached ? 1 : 0;

        $stmt = $pdo->prepare(
            'INSERT INTO complaints (reference_number, student_id, department_id, category_id, assigned_to, title, description, priority, status, sla_deadline, sla_breached, escalated_at, escalation_count, resolution_note, resolved_at, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $refNum, $sId, $deptId, $catId, $assignedTo, $title, $desc, $priority, $status,
            $slaDeadline, $isBreached, $escalatedAt, $escCount, $resNote, $resolvedAt, $createdAt
        ]);
        $cmpId = (int)$pdo->lastInsertId();

        // Audit Trail 1: Ticket created
        $pdo->prepare('INSERT INTO complaint_updates (complaint_id, updated_by, old_status, new_status, note, created_at) VALUES (?, ?, NULL, "open", "Complaint ticket registered by student", ?)')
            ->execute([$cmpId, $sId, $createdAt]);

        // Audit Trail 2: Assignment
        if ($assignedTo) {
            $assignedTime = date('Y-m-d H:i:s', strtotime("{$createdAt} + 1 hour"));
            $pdo->prepare('INSERT INTO complaint_updates (complaint_id, updated_by, old_status, new_status, note, created_at) VALUES (?, 2, "open", "in_progress", "Assigned to departmental resolution officer", ?)')
                ->execute([$cmpId, $assignedTime]);
        }

        // Audit Trail 3: Escalation
        if ($isBreached) {
            $pdo->prepare('INSERT INTO complaint_updates (complaint_id, updated_by, old_status, new_status, note, created_at) VALUES (?, 1, "in_progress", "escalated", "Automated SLA breach: priority bumped to CRITICAL", ?)')
                ->execute([$cmpId, $escalatedAt]);

            $pdo->prepare('INSERT INTO complaint_escalations (complaint_id, escalated_from, escalated_to, reason, notified_user, created_at) VALUES (?, ?, "critical", "SLA deadline exceeded", 1, ?)')
                ->execute([$cmpId, $priority, $escalatedAt]);
        }

        // Audit Trail 4: Resolution
        if ($resolvedAt) {
            $pdo->prepare('INSERT INTO complaint_updates (complaint_id, updated_by, old_status, new_status, note, created_at) VALUES (?, ?, "in_progress", "resolved", ?, ?)')
                ->execute([$cmpId, $assignedTo ?: 2, $resNote, $resolvedAt]);

            // Post Resolution Survey
            $surveyRating = rand(4, 5);
            $surveyComments = [
                'Problem was resolved much faster than expected! Very satisfied.',
                'The technician explained what went wrong and fixed it properly.',
                'Good service, thanks for the timely follow up.',
                'Resolved within hours, great accountability.'
            ];
            $pdo->prepare('INSERT INTO post_resolution_surveys (complaint_id, student_id, rating, comment, submitted_at) VALUES (?, ?, ?, ?, DATE_ADD(?, INTERVAL 2 HOUR))')
                ->execute([$cmpId, $sId, $surveyRating, $surveyComments[array_rand($surveyComments)], $resolvedAt]);
        }

        // Notifications
        $pdo->prepare('INSERT INTO notifications (user_id, title, message, type, related_id, is_read, created_at) VALUES (?, ?, ?, "complaint", ?, 0, ?)')
            ->execute([$sId, "Update on {$refNum}", "Your ticket {$refNum} status is currently: {$status}", $cmpId, $createdAt]);
    }
    logMsg("  + Inserted " . count($complaintTemplates) . " complaint tickets with audit logs, SLA countdowns, and surveys.");
}

// ── 4. Seed Admin Notifications ─────────────────────────────
$adminNotifications = [
    [1, '🚨 Critical SLA Breach', 'Ticket FP-2026-000001 (Wi-Fi Core Switch Down) exceeded its 24h deadline.', 'sla', 1],
    [1, '🚨 Escalation Alert', 'Ticket FP-2026-000007 (Shuttle Bus Breakdown) was auto-escalated by SLA engine.', 'sla', 7],
    [1, '📋 New Moderation Queue', '5 student reviews are waiting for approval in the Moderation queue.', 'system', null],
    [2, '🚨 SLA Warning', 'Ticket FP-2026-000002 has 4 hours remaining before breach.', 'sla', 2],
];

foreach ($adminNotifications as [$uId, $title, $msg, $type, $relId]) {
    $pdo->prepare('INSERT INTO notifications (user_id, title, message, type, related_id, is_read, created_at) VALUES (?, ?, ?, ?, ?, 0, NOW())')
        ->execute([$uId, $title, $msg, $type, $relId]);
}
logMsg("  + Inserted admin and supervisor notifications.");

logMsg("🎉 Data generation complete! All dashboards and charts are now populated.");

if (!$isCli) {
    echo '<br><a href="../admin/dashboard.php" class="btn">View Admin Command Center &rarr;</a> ';
    echo '<a href="../student/dashboard.php" class="btn">View Student Dashboard &rarr;</a>';
    echo '</body></html>';
}
