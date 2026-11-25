<?php

namespace Models;

use Config\Database;
use PDO;

class NotebookTask
{
    /**
     * Get all tasks for a specific user, ordered by due_date ASC, due_time ASC.
     */
    public static function allByUser(int $userId): array
    {
        $stmt = Database::getInstance()->prepare(
            'SELECT * FROM notebook_tasks WHERE user_id = ? ORDER BY due_date ASC, (due_time IS NULL), due_time ASC, id DESC'
        );
        $stmt->execute([$userId]);
        return array_map([self::class, 'hydrateRow'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Get tasks for a specific user within a date range.
     */
    public static function getByUserAndRange(int $userId, string $startDate, string $endDate): array
    {
        $stmt = Database::getInstance()->prepare(
            'SELECT * FROM notebook_tasks
             WHERE user_id = ? AND due_date <= ? AND COALESCE(end_date, due_date) >= ?
             ORDER BY due_date ASC, (due_time IS NULL), due_time ASC'
        );
        $stmt->execute([$userId, $endDate, $startDate]);
        return array_map([self::class, 'hydrateRow'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Find a task by id ensuring it belongs to the given user.
     */
    public static function findByUser(int $id, int $userId): ?array
    {
        $stmt = Database::getInstance()->prepare(
            'SELECT * FROM notebook_tasks WHERE id = ? AND user_id = ? LIMIT 1'
        );
        $stmt->execute([$id, $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? self::hydrateRow($row) : null;
    }

    /**
     * Create a new task for user.
     */
    public static function create(array $data): int
    {
        $db = Database::getInstance();
        $stmt = $db->prepare(
            'INSERT INTO notebook_tasks (
                user_id, title, notes, due_date, end_date, due_time, duration_minutes,
                priority, status, tag, theme, color, is_pinned,
                email_reminder, reminder_freq, reminder_lead, reminder_send_time, reminder_send_time_2, reminder_send_time_3, reminder_days, reminder_emails
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['user_id'],
            $data['title'],
            $data['notes'] ?? null,
            $data['due_date'],
            !empty($data['end_date']) ? $data['end_date'] : $data['due_date'],
            !empty($data['due_time']) ? $data['due_time'] : null,
            (int) ($data['duration_minutes'] ?? 60),
            $data['priority'] ?? 'Medium',
            $data['status'] ?? 'Todo',
            $data['tag'] ?? '#Personal',
            $data['theme'] ?? 'theme-green',
            $data['color'] ?? '#217346',
            !empty($data['is_pinned']) ? 1 : 0,
            !empty($data['email_reminder']) ? 1 : 0,
            $data['reminder_freq'] ?? 'once',
            $data['reminder_lead'] ?? '1h',
            !empty($data['reminder_send_time']) ? $data['reminder_send_time'] : null,
            !empty($data['reminder_send_time_2']) ? $data['reminder_send_time_2'] : null,
            !empty($data['reminder_send_time_3']) ? $data['reminder_send_time_3'] : null,
            $data['reminder_days'] ?? null,
            $data['reminder_emails'] ?? null,
        ]);
        return (int) $db->lastInsertId();
    }

    /**
     * Update an existing task ensuring user ownership.
     */
    public static function update(int $id, int $userId, array $data): bool
    {
        $stmt = Database::getInstance()->prepare(
            'UPDATE notebook_tasks
             SET title = ?, notes = ?, due_date = ?, end_date = ?, due_time = ?, duration_minutes = ?,
                 priority = ?, status = ?, tag = ?, theme = ?, color = ?, is_pinned = ?,
                 email_reminder = ?, reminder_freq = ?, reminder_lead = ?,
                 reminder_send_time = ?, reminder_send_time_2 = ?, reminder_send_time_3 = ?, reminder_days = ?, reminder_emails = ?
             WHERE id = ? AND user_id = ?'
        );
        return $stmt->execute([
            $data['title'],
            $data['notes'] ?? null,
            $data['due_date'],
            !empty($data['end_date']) ? $data['end_date'] : $data['due_date'],
            !empty($data['due_time']) ? $data['due_time'] : null,
            (int) ($data['duration_minutes'] ?? 60),
            $data['priority'] ?? 'Medium',
            $data['status'] ?? 'Todo',
            $data['tag'] ?? '#Personal',
            $data['theme'] ?? 'theme-green',
            $data['color'] ?? '#217346',
            !empty($data['is_pinned']) ? 1 : 0,
            !empty($data['email_reminder']) ? 1 : 0,
            $data['reminder_freq'] ?? 'once',
            $data['reminder_lead'] ?? '1h',
            !empty($data['reminder_send_time']) ? $data['reminder_send_time'] : null,
            !empty($data['reminder_send_time_2']) ? $data['reminder_send_time_2'] : null,
            !empty($data['reminder_send_time_3']) ? $data['reminder_send_time_3'] : null,
            $data['reminder_days'] ?? null,
            $data['reminder_emails'] ?? null,
            $id,
            $userId,
        ]);
    }

    /**
     * Toggle or update only status.
     */
    public static function updateStatus(int $id, int $userId, string $status): bool
    {
        $stmt = Database::getInstance()->prepare(
            'UPDATE notebook_tasks SET status = ? WHERE id = ? AND user_id = ?'
        );
        return $stmt->execute([$status, $id, $userId]);
    }

    /**
     * Delete a task ensuring user ownership.
     */
    public static function delete(int $id, int $userId): bool
    {
        $stmt = Database::getInstance()->prepare(
            'DELETE FROM notebook_tasks WHERE id = ? AND user_id = ?'
        );
        return $stmt->execute([$id, $userId]);
    }

    /**
     * Get notebook email/digest settings for a user.
     */
    public static function getSettings(int $userId, string $fallbackEmail = ''): array
    {
        $stmt = Database::getInstance()->prepare(
            'SELECT * FROM notebook_settings WHERE user_id = ? LIMIT 1'
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $defaultPrefs = [
            'default_view'     => 'month',
            'default_duration' => 60,
            'default_priority' => 'Medium',
            'default_theme'    => 'theme-green',
            'week_start'       => 'sun',
            'show_greeting'    => true,
        ];

        if (!$row) {
            $defaultEmails = filter_var($fallbackEmail, FILTER_VALIDATE_EMAIL) ? [$fallbackEmail] : [];
            return [
                'user_id'        => $userId,
                'emails'         => $defaultEmails,
                'digest_enabled' => true,
                'digest_freq'    => 'workdays',
                'digest_days'    => ['Sen', 'Sel', 'Rab', 'Kam', 'Jum'],
                'digest_time'    => '07:30',
                'digest_time_2'  => '',
                'digest_time_3'  => '',
                'preferences'    => $defaultPrefs,
            ];
        }

        $emails = json_decode((string) ($row['emails'] ?? '[]'), true);
        if (!is_array($emails)) {
            $emails = [];
        }
        $days = array_values(array_filter(array_map('trim', explode(',', (string) ($row['digest_days'] ?? 'Sen,Sel,Rab,Kam,Jum')))));
        $prefs = json_decode((string) ($row['preferences'] ?? '{}'), true);
        if (!is_array($prefs)) {
            $prefs = [];
        }

        return [
            'user_id'        => $userId,
            'emails'         => $emails,
            'digest_enabled' => !empty($row['digest_enabled']),
            'digest_freq'    => $row['digest_freq'] ?? 'workdays',
            'digest_days'    => $days,
            'digest_time'    => substr((string) ($row['digest_time'] ?? '07:30:00'), 0, 5),
            'digest_time_2'  => !empty($row['digest_time_2']) ? substr((string) $row['digest_time_2'], 0, 5) : '',
            'digest_time_3'  => !empty($row['digest_time_3']) ? substr((string) $row['digest_time_3'], 0, 5) : '',
            'preferences'    => array_merge($defaultPrefs, $prefs),
        ];
    }

    /**
     * Save notebook email/digest settings & preferences for a user.
     */
    public static function saveSettings(int $userId, array $data): bool
    {
        $emailsJson = json_encode(array_values($data['emails'] ?? []), JSON_UNESCAPED_UNICODE);
        $daysCsv    = implode(',', $data['digest_days'] ?? ['Sen', 'Sel', 'Rab', 'Kam', 'Jum']);
        $time       = preg_match('/^\d{2}:\d{2}/', (string) ($data['digest_time'] ?? '')) ? substr((string) $data['digest_time'], 0, 5) . ':00' : '07:30:00';
        $time2      = preg_match('/^\d{2}:\d{2}/', (string) ($data['digest_time_2'] ?? '')) ? substr((string) $data['digest_time_2'], 0, 5) . ':00' : null;
        $time3      = preg_match('/^\d{2}:\d{2}/', (string) ($data['digest_time_3'] ?? '')) ? substr((string) $data['digest_time_3'], 0, 5) . ':00' : null;
        $freq       = in_array($data['digest_freq'] ?? '', ['workdays', 'daily', 'custom'], true) ? $data['digest_freq'] : 'workdays';
        $enabled    = !empty($data['digest_enabled']) ? 1 : 0;
        $prefsJson  = json_encode($data['preferences'] ?? [], JSON_UNESCAPED_UNICODE);

        $stmt = Database::getInstance()->prepare(
            'INSERT INTO notebook_settings (user_id, emails, digest_enabled, digest_freq, digest_days, digest_time, digest_time_2, digest_time_3, preferences)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                emails = VALUES(emails),
                digest_enabled = VALUES(digest_enabled),
                digest_freq = VALUES(digest_freq),
                digest_days = VALUES(digest_days),
                digest_time = VALUES(digest_time),
                digest_time_2 = VALUES(digest_time_2),
                digest_time_3 = VALUES(digest_time_3),
                preferences = VALUES(preferences)'
        );

        return $stmt->execute([$userId, $emailsJson, $enabled, $freq, $daysCsv, $time, $time2, $time3, $prefsJson]);
    }

    private static function hydrateRow(array $row): array
    {
        $emails = json_decode((string) ($row['reminder_emails'] ?? '[]'), true);
        if (!is_array($emails)) {
            $emails = [];
        }
        $days = array_values(array_filter(array_map('trim', explode(',', (string) ($row['reminder_days'] ?? '')))));

        $row['id']                 = (int) $row['id'];
        $row['user_id']            = (int) $row['user_id'];
        $row['duration_minutes']   = (int) ($row['duration_minutes'] ?? 60);
        $row['is_pinned']          = !empty($row['is_pinned']);
        $row['email_reminder']     = !empty($row['email_reminder']);
        $row['reminder_emails']    = $emails;
        $row['reminder_days_arr']  = $days;
        $row['due_time']           = $row['due_time'] ? substr((string) $row['due_time'], 0, 5) : '';
        $row['end_date']           = !empty($row['end_date']) ? $row['end_date'] : $row['due_date'];
        $row['reminder_send_time']   = !empty($row['reminder_send_time']) ? substr((string) $row['reminder_send_time'], 0, 5) : '08:00';
        $row['reminder_send_time_2'] = !empty($row['reminder_send_time_2']) ? substr((string) $row['reminder_send_time_2'], 0, 5) : '';
        $row['reminder_send_time_3'] = !empty($row['reminder_send_time_3']) ? substr((string) $row['reminder_send_time_3'], 0, 5) : '';

        return $row;
    }
}
