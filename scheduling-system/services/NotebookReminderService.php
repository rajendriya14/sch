<?php

namespace Services;

use Config\Database;
use PDO;

/**
 * Background runner for Personal Notebook email reminders & daily briefings.
 */
class NotebookReminderService
{
    private const DAY_MAP = [
        1 => 'Sen',
        2 => 'Sel',
        3 => 'Rab',
        4 => 'Kam',
        5 => 'Jum',
        6 => 'Sab',
        7 => 'Min',
    ];

    /**
     * Process all due task reminders and daily briefings (WIB / Asia/Jakarta).
     *
     * @return array{tasks_sent: int, briefings_sent: int, errors: string[]}
     */
    public static function run(): array
    {
        $tz       = new \DateTimeZone('Asia/Jakarta');
        $now      = new \DateTimeImmutable('now', $tz);
        $today    = $now->format('Y-m-d');
        $nowTime  = $now->format('H:i');
        $dayNum   = (int) $now->format('N'); // 1 (Mon) .. 7 (Sun)
        $dayLabel = self::DAY_MAP[$dayNum] ?? 'Sen';

        $db     = Database::getInstance();
        $mail   = new MailService();
        $result = ['tasks_sent' => 0, 'briefings_sent' => 0, 'errors' => []];

        // 1. Per-Task Reminders
        $stmt = $db->query(
            "SELECT t.*, u.username, u.email AS user_email
             FROM notebook_tasks t
             LEFT JOIN users u ON u.id = t.user_id
             WHERE t.email_reminder = 1 AND t.status <> 'Done'"
        );
        $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($tasks as $t) {
            $recipients = json_decode((string) ($t['reminder_emails'] ?? '[]'), true);
            if (!is_array($recipients) || empty($recipients)) {
                if (!empty($t['user_email']) && filter_var($t['user_email'], FILTER_VALIDATE_EMAIL)) {
                    $recipients = [$t['user_email']];
                } else {
                    continue;
                }
            }

            $freq     = $t['reminder_freq'] ?? 'once';
            $lastSent = $t['last_reminder_sent_at'] ?? null;
            $dueDate  = $t['due_date'];
            $endDate  = !empty($t['end_date']) ? $t['end_date'] : $dueDate;
            $shouldSend = false;

            if ($freq === 'once') {
                if (!empty($lastSent)) {
                    continue;
                }
                $dueTimeStr = !empty($t['due_time']) ? substr((string) $t['due_time'], 0, 5) : '09:00';
                $dueDt      = new \DateTimeImmutable("{$dueDate} {$dueTimeStr}:00", $tz);
                $leadMap    = ['15m' => '-15 minutes', '1h' => '-1 hour', '1d' => '-1 day'];
                $triggerDt  = $dueDt->modify($leadMap[$t['reminder_lead'] ?? '1h'] ?? '-1 hour');
                if ($now >= $triggerDt) {
                    $shouldSend = true;
                }
            } else {
                // Recurring: up to 3 daily time slots within [due_date .. end_date]
                if ($today < $dueDate || $today > $endDate) {
                    continue;
                }
                if (!self::matchesDayPattern($freq, $dayNum, $dayLabel, (string) ($t['reminder_days'] ?? ''))) {
                    continue;
                }
                $sentSlots = array_filter(explode(',', (string) ($t['last_sent_slots'] ?? '')));
                $times = array_unique(array_filter([
                    !empty($t['reminder_send_time']) ? substr((string) $t['reminder_send_time'], 0, 5) : '08:00',
                    !empty($t['reminder_send_time_2']) ? substr((string) $t['reminder_send_time_2'], 0, 5) : '',
                    !empty($t['reminder_send_time_3']) ? substr((string) $t['reminder_send_time_3'], 0, 5) : '',
                ]));
                $matchedSlot = null;
                foreach ($times as $tm) {
                    $slotKey = "{$today}@{$tm}";
                    if ($nowTime >= $tm && !in_array($slotKey, $sentSlots, true)) {
                        $matchedSlot = $slotKey;
                        break;
                    }
                }
                if ($matchedSlot === null) {
                    continue;
                }
                $shouldSend = true;
            }

            if (!$shouldSend) {
                continue;
            }

            $html = self::buildTaskEmailHtml($t);
            $sendRes = $mail->send($recipients, '[Notebook Reminder] ' . $t['title'], $html);
            if ($sendRes['success']) {
                if (isset($matchedSlot)) {
                    $sentSlots[] = $matchedSlot;
                    $sentSlots = array_values(array_filter($sentSlots, static fn($s) => strpos($s, $today) === 0));
                    $upd = $db->prepare('UPDATE notebook_tasks SET last_reminder_sent_at = ?, last_sent_slots = ? WHERE id = ?');
                    $upd->execute([$now->format('Y-m-d H:i:s'), implode(',', $sentSlots), $t['id']]);
                    unset($matchedSlot);
                } else {
                    $upd = $db->prepare('UPDATE notebook_tasks SET last_reminder_sent_at = ? WHERE id = ?');
                    $upd->execute([$now->format('Y-m-d H:i:s'), $t['id']]);
                }
                $result['tasks_sent']++;
            } else {
                $result['errors'][] = "Task #{$t['id']}: " . $sendRes['message'];
            }
        }

        // 2. Daily Briefings
        $sStmt = $db->query('SELECT * FROM notebook_settings WHERE digest_enabled = 1');
        $settingsRows = $sStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($settingsRows as $s) {
            $emails = json_decode((string) ($s['emails'] ?? '[]'), true);
            if (!is_array($emails) || empty($emails)) {
                continue;
            }
            if (!self::matchesDayPattern($s['digest_freq'] ?? 'workdays', $dayNum, $dayLabel, (string) ($s['digest_days'] ?? ''))) {
                continue;
            }
            $sentSlots = array_filter(explode(',', (string) ($s['last_digest_slots'] ?? '')));
            $times = array_unique(array_filter([
                substr((string) ($s['digest_time'] ?? '07:30:00'), 0, 5),
                !empty($s['digest_time_2']) ? substr((string) $s['digest_time_2'], 0, 5) : '',
                !empty($s['digest_time_3']) ? substr((string) $s['digest_time_3'], 0, 5) : '',
            ]));
            $matchedSlot = null;
            foreach ($times as $tm) {
                $slotKey = "{$today}@{$tm}";
                if ($nowTime >= $tm && !in_array($slotKey, $sentSlots, true)) {
                    $matchedSlot = $slotKey;
                    break;
                }
            }
            if ($matchedSlot === null) {
                continue;
            }

            $tStmt = $db->prepare(
                "SELECT * FROM notebook_tasks
                 WHERE user_id = ? AND status <> 'Done' AND due_date <= ? AND COALESCE(end_date, due_date) >= ?
                 ORDER BY (due_time IS NULL), due_time ASC"
            );
            $tStmt->execute([$s['user_id'], $today, $today]);
            $dayTasks = $tStmt->fetchAll(PDO::FETCH_ASSOC);
            if (empty($dayTasks)) {
                continue;
            }

            $html = self::buildDigestEmailHtml($dayTasks, $today);
            $sendRes = $mail->send($emails, "[Personal Notebook] Briefing Agenda ({$today})", $html);
            if ($sendRes['success']) {
                $sentSlots[] = $matchedSlot;
                $sentSlots = array_values(array_filter($sentSlots, static fn($x) => strpos($x, $today) === 0));
                $upd = $db->prepare('UPDATE notebook_settings SET last_digest_sent_date = ?, last_digest_slots = ? WHERE user_id = ?');
                $upd->execute([$today, implode(',', $sentSlots), $s['user_id']]);
                $result['briefings_sent']++;
            } else {
                $result['errors'][] = "Briefing user #{$s['user_id']}: " . $sendRes['message'];
            }
        }

        // 3. Daily Sticky Note Reminders (up to 3 time slots)
        $stStmt = $db->query(
            "SELECT sn.*, u.email AS user_email, ns.emails AS settings_emails
             FROM notebook_sticky_notes sn
             LEFT JOIN users u ON u.id = sn.user_id
             LEFT JOIN notebook_settings ns ON ns.user_id = sn.user_id
             WHERE (sn.reminder_time IS NOT NULL AND sn.reminder_time <> '')
                OR (sn.reminder_time_2 IS NOT NULL AND sn.reminder_time_2 <> '')
                OR (sn.reminder_time_3 IS NOT NULL AND sn.reminder_time_3 <> '')"
        );
        $stickies = $stStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($stickies as $sn) {
            $sentSlots = array_filter(explode(',', (string) ($sn['last_sent_slots'] ?? '')));
            $times = array_unique(array_filter([
                !empty($sn['reminder_time']) ? substr((string) $sn['reminder_time'], 0, 5) : '',
                !empty($sn['reminder_time_2']) ? substr((string) $sn['reminder_time_2'], 0, 5) : '',
                !empty($sn['reminder_time_3']) ? substr((string) $sn['reminder_time_3'], 0, 5) : '',
            ]));
            $matchedSlot = null;
            $matchedTime = '';
            foreach ($times as $tm) {
                $slotKey = "{$today}@{$tm}";
                if ($nowTime >= $tm && !in_array($slotKey, $sentSlots, true)) {
                    $matchedSlot = $slotKey;
                    $matchedTime = $tm;
                    break;
                }
            }
            if ($matchedSlot === null) {
                continue;
            }
            $recipients = json_decode((string) ($sn['reminder_emails'] ?? '[]'), true);
            if (!is_array($recipients) || empty($recipients)) {
                $recipients = json_decode((string) ($sn['settings_emails'] ?? '[]'), true);
            }
            if (!is_array($recipients) || empty($recipients)) {
                if (!empty($sn['user_email']) && filter_var($sn['user_email'], FILTER_VALIDATE_EMAIL)) {
                    $recipients = [$sn['user_email']];
                } else {
                    continue;
                }
            }

            $sTitle = htmlspecialchars((string) $sn['title'], ENT_QUOTES, 'UTF-8');
            $sBody  = nl2br(htmlspecialchars((string) $sn['content'], ENT_QUOTES, 'UTF-8'));
            $sTime  = htmlspecialchars($matchedTime, ENT_QUOTES, 'UTF-8');
            $html   = '<div style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;border:1px solid #e5e7eb;border-radius:10px;overflow:hidden;">'
                . '<div style="background:#217346;color:#fff;padding:16px 20px;"><strong>Sticky Note Reminder (' . $sTime . ' WIB)</strong></div>'
                . '<div style="padding:20px;font-size:14px;color:#111827;">'
                . "<h3 style='margin:0 0 10px 0;'>{$sTitle}</h3>"
                . "<div style='background:#fef9c3;border:1px solid #fde68a;border-radius:8px;padding:12px 14px;margin-bottom:16px;'>{$sBody}</div>"
                . '<a href="https://schedulewfm.iferror.web.id/index.php?page=notebook" style="display:inline-block;background:#217346;color:#fff;text-decoration:none;padding:9px 16px;border-radius:6px;font-size:13px;font-weight:bold;">Buka Personal Notebook</a>'
                . '</div></div>';

            $sendRes = $mail->send($recipients, '[Sticky Note Reminder] ' . $sn['title'], $html);
            if ($sendRes['success']) {
                $sentSlots[] = $matchedSlot;
                $sentSlots = array_values(array_filter($sentSlots, static fn($x) => strpos($x, $today) === 0));
                $upd = $db->prepare('UPDATE notebook_sticky_notes SET last_reminder_sent_date = ?, last_sent_slots = ? WHERE id = ?');
                $upd->execute([$today, implode(',', $sentSlots), $sn['id']]);
                $result['tasks_sent']++;
            } else {
                $result['errors'][] = "Sticky #{$sn['id']}: " . $sendRes['message'];
            }
        }

        return $result;
    }

    private static function matchesDayPattern(string $freq, int $dayNum, string $dayLabel, string $customCsv): bool
    {
        if ($freq === 'daily') {
            return true;
        }
        if ($freq === 'workdays') {
            return $dayNum >= 1 && $dayNum <= 5;
        }
        $days = array_filter(array_map('trim', explode(',', $customCsv)));
        return in_array($dayLabel, $days, true);
    }

    private static function buildTaskEmailHtml(array $t): string
    {
        $title = htmlspecialchars((string) $t['title'], ENT_QUOTES, 'UTF-8');
        $notes = nl2br(htmlspecialchars((string) ($t['notes'] ?? ''), ENT_QUOTES, 'UTF-8'));
        $date  = htmlspecialchars((string) $t['due_date'], ENT_QUOTES, 'UTF-8');
        $end   = !empty($t['end_date']) && $t['end_date'] !== $t['due_date'] ? ' s/d ' . htmlspecialchars((string) $t['end_date'], ENT_QUOTES, 'UTF-8') : '';
        $time  = !empty($t['due_time']) ? ' · ' . substr((string) $t['due_time'], 0, 5) . ' WIB' : '';
        $prio  = htmlspecialchars((string) ($t['priority'] ?? 'Medium'), ENT_QUOTES, 'UTF-8');

        return '<div style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;border:1px solid #e5e7eb;border-radius:10px;overflow:hidden;">'
            . '<div style="background:#217346;color:#fff;padding:16px 20px;"><strong>Personal Notebook Reminder</strong></div>'
            . '<div style="padding:20px;font-size:14px;color:#111827;">'
            . "<h3 style='margin:0 0 8px 0;'>{$title}</h3>"
            . "<div style='color:#6b7280;font-size:13px;margin-bottom:12px;'>Jadwal: {$date}{$end}{$time} &middot; Prioritas: <strong>{$prio}</strong></div>"
            . ($notes !== '' ? "<div style='background:#f9fafb;border-left:4px solid #217346;padding:10px 12px;margin-bottom:16px;'>{$notes}</div>" : '')
            . '<a href="https://schedulewfm.iferror.web.id/index.php?page=notebook" style="display:inline-block;background:#217346;color:#fff;text-decoration:none;padding:9px 16px;border-radius:6px;font-size:13px;font-weight:bold;">Buka Personal Notebook</a>'
            . '</div></div>';
    }

    private static function buildDigestEmailHtml(array $tasks, string $today): string
    {
        $items = '';
        foreach ($tasks as $t) {
            $title = htmlspecialchars((string) $t['title'], ENT_QUOTES, 'UTF-8');
            $prio  = htmlspecialchars((string) ($t['priority'] ?? 'Medium'), ENT_QUOTES, 'UTF-8');
            $time  = !empty($t['due_time']) ? substr((string) $t['due_time'], 0, 5) . ' WIB' : 'Seharian';
            $items .= "<div style='border:1px solid #e5e7eb;border-left:4px solid #217346;border-radius:6px;padding:10px 12px;margin-bottom:8px;'>"
                . "<strong>{$title}</strong> <span style='font-size:12px;color:#6b7280;'>[{$prio}]</span>"
                . "<div style='font-size:12px;color:#6b7280;'>{$time}</div></div>";
        }

        return '<div style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;border:1px solid #e5e7eb;border-radius:10px;overflow:hidden;">'
            . "<div style='background:#217346;color:#fff;padding:16px 20px;'><strong>Briefing Agenda Hari Ini ({$today})</strong></div>"
            . "<div style='padding:20px;font-size:14px;color:#111827;'>{$items}"
            . '<div style="margin-top:16px;"><a href="https://schedulewfm.iferror.web.id/index.php?page=notebook" style="display:inline-block;background:#217346;color:#fff;text-decoration:none;padding:9px 16px;border-radius:6px;font-size:13px;font-weight:bold;">Buka Personal Notebook</a></div>'
            . '</div></div>';
    }
}
