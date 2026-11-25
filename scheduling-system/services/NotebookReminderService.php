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
        $nowMins  = ((int) $now->format('H')) * 60 + (int) $now->format('i');
        $dayNum   = (int) $now->format('N'); // 1 (Mon) .. 7 (Sun)
        $dayLabel = self::DAY_MAP[$dayNum] ?? 'Sen';

        $db     = Database::getInstance();
        $mail   = new MailService();
        $result = ['tasks_sent' => 0, 'briefings_sent' => 0, 'errors' => []];

        // 1. Per-Task Reminders
        $stmt = $db->query(
            "SELECT t.*, u.username, u.display_name AS user_name, ns.emails AS settings_emails
             FROM notebook_tasks t
             LEFT JOIN users u ON u.id = t.user_id
             LEFT JOIN notebook_settings ns ON ns.user_id = t.user_id
             WHERE t.email_reminder = 1 AND t.status <> 'Done'"
        );
        $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($tasks as $t) {
            $recipients = json_decode((string) ($t['reminder_emails'] ?? '[]'), true);
            if (!is_array($recipients) || empty($recipients)) {
                $recipients = json_decode((string) ($t['settings_emails'] ?? '[]'), true);
            }
            if (!is_array($recipients) || empty($recipients)) {
                continue;
            }

            $freq     = $t['reminder_freq'] ?? 'once';
            $lastSent = $t['last_reminder_sent_at'] ?? null;
            $dueDate  = $t['due_date'];
            $endDate  = !empty($t['end_date']) ? $t['end_date'] : $dueDate;

            if ($freq === 'once') {
                if (!empty($lastSent)) {
                    continue;
                }
                $dueTimeStr = !empty($t['due_time']) ? substr((string) $t['due_time'], 0, 5) : '09:00';
                $dueDt      = new \DateTimeImmutable("{$dueDate} {$dueTimeStr}:00", $tz);
                $leadMap    = ['15m' => '-15 minutes', '1h' => '-1 hour', '1d' => '-1 day'];
                $triggerDt  = $dueDt->modify($leadMap[$t['reminder_lead'] ?? '1h'] ?? '-1 hour');
                if ($now < $triggerDt) {
                    continue;
                }

                $html = self::buildTaskEmailHtml($t, (string) $recipients[0]);
                $allOk = true;
                foreach ($recipients as $rcpt) {
                    $sendRes = $mail->send($rcpt, '[Notebook Reminder] ' . $t['title'], self::buildTaskEmailHtml($t, (string) $rcpt));
                    if (!$sendRes['success']) {
                        $allOk = false;
                        $result['errors'][] = "Task #{$t['id']} ({$rcpt}): " . $sendRes['message'];
                    }
                }
                if ($allOk) {
                    $upd = $db->prepare('UPDATE notebook_tasks SET last_reminder_sent_at = ? WHERE id = ?');
                    $upd->execute([$now->format('Y-m-d H:i:s'), $t['id']]);
                    $result['tasks_sent']++;
                }
            } else {
                // Recurring: up to 3 daily time slots within [due_date .. end_date]
                if ($today < $dueDate || $today > $endDate) {
                    continue;
                }
                if (!self::matchesDayPattern($freq, $dayNum, $dayLabel, (string) ($t['reminder_days'] ?? ''))) {
                    continue;
                }
                $sentSlots = array_values(array_filter(explode(',', (string) ($t['last_sent_slots'] ?? '')), static fn($s) => strpos($s, $today) === 0));
                $times = array_unique(array_filter([
                    !empty($t['reminder_send_time']) ? substr((string) $t['reminder_send_time'], 0, 5) : '08:00',
                    !empty($t['reminder_send_time_2']) ? substr((string) $t['reminder_send_time_2'], 0, 5) : '',
                    !empty($t['reminder_send_time_3']) ? substr((string) $t['reminder_send_time_3'], 0, 5) : '',
                ]));

                $dueSlots = self::findDueSlots($times, $nowMins, $today, $sentSlots);
                foreach ($dueSlots as $slotKey) {
                    $allOk = true;
                    foreach ($recipients as $rcpt) {
                        $sendRes = $mail->send($rcpt, '[Notebook Reminder] ' . $t['title'], self::buildTaskEmailHtml($t, (string) $rcpt));
                        if (!$sendRes['success']) {
                            $allOk = false;
                            $result['errors'][] = "Task #{$t['id']} ({$rcpt}): " . $sendRes['message'];
                        }
                    }
                    if ($allOk) {
                        $sentSlots[] = $slotKey;
                        $upd = $db->prepare('UPDATE notebook_tasks SET last_reminder_sent_at = ?, last_sent_slots = ? WHERE id = ?');
                        $upd->execute([$now->format('Y-m-d H:i:s'), implode(',', $sentSlots), $t['id']]);
                        $result['tasks_sent']++;
                    }
                }
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
            $sentSlots = array_values(array_filter(explode(',', (string) ($s['last_digest_slots'] ?? '')), static fn($x) => strpos($x, $today) === 0));
            $times = array_unique(array_filter([
                substr((string) ($s['digest_time'] ?? '07:30:00'), 0, 5),
                !empty($s['digest_time_2']) ? substr((string) $s['digest_time_2'], 0, 5) : '',
                !empty($s['digest_time_3']) ? substr((string) $s['digest_time_3'], 0, 5) : '',
            ]));

            $dueSlots = self::findDueSlots($times, $nowMins, $today, $sentSlots);
            if (empty($dueSlots)) {
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

            foreach ($dueSlots as $slotKey) {
                $allOk = true;
                foreach ($emails as $rcpt) {
                    $sendRes = $mail->send($rcpt, "[Personal Notebook] Briefing Agenda ({$today})", self::buildDigestEmailHtml($dayTasks, $today, (string) $rcpt));
                    if (!$sendRes['success']) {
                        $allOk = false;
                        $result['errors'][] = "Briefing user #{$s['user_id']} ({$rcpt}): " . $sendRes['message'];
                    }
                }
                if ($allOk) {
                    $sentSlots[] = $slotKey;
                    $upd = $db->prepare('UPDATE notebook_settings SET last_digest_sent_date = ?, last_digest_slots = ? WHERE user_id = ?');
                    $upd->execute([$today, implode(',', $sentSlots), $s['user_id']]);
                    $result['briefings_sent']++;
                }
            }
        }

        // 3. Daily Sticky Note Reminders (up to 3 time slots + day pattern)
        $stStmt = $db->query(
            "SELECT sn.*, u.display_name AS user_name, u.username, ns.emails AS settings_emails
             FROM notebook_sticky_notes sn
             LEFT JOIN users u ON u.id = sn.user_id
             LEFT JOIN notebook_settings ns ON ns.user_id = sn.user_id
             WHERE sn.email_reminder = 1
               AND ((sn.reminder_time IS NOT NULL AND sn.reminder_time <> '')
                 OR (sn.reminder_time_2 IS NOT NULL AND sn.reminder_time_2 <> '')
                 OR (sn.reminder_time_3 IS NOT NULL AND sn.reminder_time_3 <> ''))"
        );
        $stickies = $stStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($stickies as $sn) {
            if (!self::matchesDayPattern($sn['reminder_freq'] ?? 'daily', $dayNum, $dayLabel, (string) ($sn['reminder_days'] ?? ''))) {
                continue;
            }
            $sentSlots = array_values(array_filter(explode(',', (string) ($sn['last_sent_slots'] ?? '')), static fn($x) => strpos($x, $today) === 0));
            $times = array_unique(array_filter([
                !empty($sn['reminder_time']) ? substr((string) $sn['reminder_time'], 0, 5) : '',
                !empty($sn['reminder_time_2']) ? substr((string) $sn['reminder_time_2'], 0, 5) : '',
                !empty($sn['reminder_time_3']) ? substr((string) $sn['reminder_time_3'], 0, 5) : '',
            ]));

            $dueSlots = self::findDueSlots($times, $nowMins, $today, $sentSlots);
            if (empty($dueSlots)) {
                continue;
            }

            $recipients = json_decode((string) ($sn['reminder_emails'] ?? '[]'), true);
            if (!is_array($recipients) || empty($recipients)) {
                $recipients = json_decode((string) ($sn['settings_emails'] ?? '[]'), true);
            }
            if (!is_array($recipients) || empty($recipients)) {
                continue;
            }

            foreach ($dueSlots as $slotKey) {
                $matchedTime = substr($slotKey, 11, 5);
                $sTitle = htmlspecialchars((string) $sn['title'], ENT_QUOTES, 'UTF-8');
                $sTime  = htmlspecialchars($matchedTime, ENT_QUOTES, 'UTF-8') . ' WIB';
                $itemHtml = "<div style='border:1px solid #e5e7eb;border-left:4px solid #217346;border-radius:6px;padding:10px 14px;margin-bottom:10px;'>"
                    . "<div style='font-weight:bold;color:#111827;'>1. {$sTitle}</div>"
                    . "<div style='font-size:12px;color:#6b7280;margin-top:4px;'>{$sTime}</div></div>";

                $allOk = true;
                foreach ($recipients as $rcpt) {
                    $rcptLabel = htmlspecialchars((string) $rcpt, ENT_QUOTES, 'UTF-8');
                    $html = self::wrapEmailLayout($rcptLabel, $itemHtml);
                    $sendRes = $mail->send($rcpt, '[Sticky Note Reminder] ' . $sn['title'], $html);
                    if (!$sendRes['success']) {
                        $allOk = false;
                        $result['errors'][] = "Sticky #{$sn['id']} ({$rcpt}): " . $sendRes['message'];
                    }
                }
                if ($allOk) {
                    $sentSlots[] = $slotKey;
                    $upd = $db->prepare('UPDATE notebook_sticky_notes SET last_reminder_sent_date = ?, last_sent_slots = ? WHERE id = ?');
                    $upd->execute([$today, implode(',', $sentSlots), $sn['id']]);
                    $result['tasks_sent']++;
                }
            }
        }

        return $result;
    }

    /**
     * Return safe diagnostic summary for active reminders (no secrets or content).
     */
    public static function diagnose(): array
    {
        $tz      = new \DateTimeZone('Asia/Jakarta');
        $now     = new \DateTimeImmutable('now', $tz);
        $today   = $now->format('Y-m-d');
        $nowMins = ((int) $now->format('H')) * 60 + (int) $now->format('i');
        $db      = Database::getInstance();

        $out = ['now_wib' => $now->format('Y-m-d H:i:s'), 'tasks' => [], 'stickies' => []];

        $tRows = $db->query(
            "SELECT t.id, t.due_date, t.end_date, t.status, t.email_reminder, t.reminder_freq,
                    t.reminder_send_time, t.reminder_send_time_2, t.reminder_send_time_3,
                    t.reminder_emails, t.last_sent_slots, t.last_reminder_sent_at, ns.emails AS settings_emails
             FROM notebook_tasks t
             LEFT JOIN notebook_settings ns ON ns.user_id = t.user_id
             WHERE t.email_reminder = 1 AND t.status <> 'Done'"
        )->fetchAll(PDO::FETCH_ASSOC);

        foreach ($tRows as $t) {
            $rcpt = json_decode((string) ($t['reminder_emails'] ?? '[]'), true);
            if (!is_array($rcpt) || empty($rcpt)) {
                $rcpt = json_decode((string) ($t['settings_emails'] ?? '[]'), true);
            }
            $out['tasks'][] = [
                'id'           => (int) $t['id'],
                'freq'         => $t['reminder_freq'],
                'range'        => $t['due_date'] . '..' . ($t['end_date'] ?: $t['due_date']),
                'times'        => array_values(array_filter([$t['reminder_send_time'], $t['reminder_send_time_2'], $t['reminder_send_time_3']])),
                'has_rcpt'     => is_array($rcpt) && !empty($rcpt),
                'sent_slots'   => $t['last_sent_slots'],
                'last_sent_at' => $t['last_reminder_sent_at'],
            ];
        }

        $sRows = $db->query(
            "SELECT sn.id, sn.email_reminder, sn.reminder_freq, sn.reminder_time, sn.reminder_time_2, sn.reminder_time_3,
                    sn.reminder_emails, sn.last_sent_slots, ns.emails AS settings_emails
             FROM notebook_sticky_notes sn
             LEFT JOIN notebook_settings ns ON ns.user_id = sn.user_id
             WHERE sn.email_reminder = 1"
        )->fetchAll(PDO::FETCH_ASSOC);

        foreach ($sRows as $s) {
            $rcpt = json_decode((string) ($s['reminder_emails'] ?? '[]'), true);
            if (!is_array($rcpt) || empty($rcpt)) {
                $rcpt = json_decode((string) ($s['settings_emails'] ?? '[]'), true);
            }
            $out['stickies'][] = [
                'id'         => (int) $s['id'],
                'freq'       => $s['reminder_freq'],
                'times'      => array_values(array_filter([$s['reminder_time'], $s['reminder_time_2'], $s['reminder_time_3']])),
                'has_rcpt'   => is_array($rcpt) && !empty($rcpt),
                'sent_slots' => $s['last_sent_slots'],
            ];
        }

        return $out;
    }

    /**
     * Find all time slots due within the active 2-hour window that haven't been sent today.
     *
     * @param string[] $times
     * @param string[] $sentSlots
     * @return string[]
     */
    private static function findDueSlots(array $times, int $nowMins, string $today, array $sentSlots): array
    {
        $due = [];
        foreach ($times as $tm) {
            if (!preg_match('/^(\d{2}):(\d{2})$/', $tm, $m)) {
                continue;
            }
            $slotMins = ((int) $m[1]) * 60 + (int) $m[2];
            $diff     = $nowMins - $slotMins;
            if ($diff >= 0 && $diff <= 120) {
                $slotKey = "{$today}@{$tm}";
                if (!in_array($slotKey, $sentSlots, true)) {
                    $due[] = $slotKey;
                }
            }
        }
        return $due;
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

    private static function formatTaskScheduleLine(array $t): string
    {
        $due = (string) ($t['due_date'] ?? '');
        $end = !empty($t['end_date']) ? (string) $t['end_date'] : $due;
        $timeStr = !empty($t['due_time']) ? substr((string) $t['due_time'], 0, 5) . ' WIB' : '';

        if ($due !== '' && $end !== '' && $end !== $due) {
            $d1 = date('d/m/Y', strtotime($due));
            $d2 = date('d/m/Y', strtotime($end));
            $range = "{$d1} – {$d2}";
            return $timeStr !== '' ? "{$range} &middot; {$timeStr}" : $range;
        }
        return $timeStr !== '' ? $timeStr : ($due !== '' ? date('d/m/Y', strtotime($due)) : 'Seharian');
    }

    private static function buildTaskEmailHtml(array $t, string $recipientEmail = 'Anda'): string
    {
        $title = htmlspecialchars((string) $t['title'], ENT_QUOTES, 'UTF-8');
        $sched = self::formatTaskScheduleLine($t);
        $prio  = htmlspecialchars((string) ($t['priority'] ?? 'Medium'), ENT_QUOTES, 'UTF-8');
        $uName = htmlspecialchars($recipientEmail, ENT_QUOTES, 'UTF-8');
        $prioColor = $prio === 'High' ? '#b91c1c' : '#374151';

        $itemHtml = "<div style='border:1px solid #e5e7eb;border-left:4px solid #217346;border-radius:6px;padding:10px 14px;margin-bottom:10px;'>"
            . "<div style='font-weight:bold;color:#111827;'>1. {$title} <span style='color:{$prioColor};font-size:12px;'>[{$prio}]</span></div>"
            . "<div style='font-size:12px;color:#6b7280;margin-top:4px;'>{$sched}</div></div>";

        return self::wrapEmailLayout($uName, $itemHtml);
    }

    private static function buildDigestEmailHtml(array $tasks, string $today, string $recipientEmail = 'Anda'): string
    {
        $items = '';
        $num   = 1;
        foreach ($tasks as $t) {
            $title = htmlspecialchars((string) $t['title'], ENT_QUOTES, 'UTF-8');
            $prio  = htmlspecialchars((string) ($t['priority'] ?? 'Medium'), ENT_QUOTES, 'UTF-8');
            $sched = self::formatTaskScheduleLine($t);
            $prioColor = $prio === 'High' ? '#b91c1c' : '#374151';
            $items .= "<div style='border:1px solid #e5e7eb;border-left:4px solid #217346;border-radius:6px;padding:10px 14px;margin-bottom:10px;'>"
                . "<div style='font-weight:bold;color:#111827;'>{$num}. {$title} <span style='color:{$prioColor};font-size:12px;'>[{$prio}]</span></div>"
                . "<div style='font-size:12px;color:#6b7280;margin-top:4px;'>{$sched}</div></div>";
            $num++;
        }

        return self::wrapEmailLayout(htmlspecialchars($recipientEmail, ENT_QUOTES, 'UTF-8'), $items);
    }

    private static function wrapEmailLayout(string $userName, string $itemsHtml): string
    {
        $safeDisplayRecipient = str_replace(['@', '.'], ['&#8203;@', '&#8203;.'], $userName);
        return '<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"></head>'
            . '<body style="margin:0;padding:24px;background:#f8fafc;font-family:Arial,Helvetica,sans-serif;color:#111827;">'
            . '<table width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;margin:0 auto;background:#ffffff;border:1px solid #e5e7eb;border-radius:10px;overflow:hidden;">'
            . '<tr><td style="background:#217346;color:#ffffff;padding:18px 24px;">'
            . '<div style="font-size:12px;opacity:0.9;letter-spacing:0.5px;text-transform:uppercase;">Personal Notebook &middot; Reminder Penting</div>'
            . "<div style='font-size:20px;font-weight:bold;margin-top:4px;color:#ffffff;'>Halo <span style='color:#ffffff;text-decoration:none;'>{$safeDisplayRecipient}</span>, ini catatan pengingat Anda</div>"
            . '</td></tr>'
            . "<tr><td style='padding:22px 24px;font-size:14px;line-height:1.5;'>{$itemsHtml}"
            . '<div style="margin-top:16px;"><a href="https://schedulewfm.iferror.web.id/index.php?page=notebook" style="display:inline-block;background:#217346;color:#ffffff;text-decoration:none;font-weight:bold;font-size:13px;padding:10px 18px;border-radius:6px;">Buka Personal Notebook</a></div>'
            . '</td></tr></table></body></html>';
    }
}
