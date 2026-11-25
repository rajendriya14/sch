<?php

namespace Models;

use Config\Database;
use PDO;

class NotebookStickyNote
{
    public static function allByUser(int $userId): array
    {
        $stmt = Database::getInstance()->prepare(
            'SELECT * FROM notebook_sticky_notes WHERE user_id = ? ORDER BY id DESC'
        );
        $stmt->execute([$userId]);
        return array_map([self::class, 'hydrate'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public static function findByUser(int $id, int $userId): ?array
    {
        $stmt = Database::getInstance()->prepare(
            'SELECT * FROM notebook_sticky_notes WHERE id = ? AND user_id = ? LIMIT 1'
        );
        $stmt->execute([$id, $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? self::hydrate($row) : null;
    }

    public static function create(array $data): int
    {
        $db = Database::getInstance();
        $stmt = $db->prepare(
            'INSERT INTO notebook_sticky_notes (user_id, title, content, theme, reminder_time, reminder_time_2, reminder_time_3, reminder_emails)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['user_id'],
            $data['title'],
            $data['content'],
            $data['theme'] ?? 'sticky-yellow',
            !empty($data['reminder_time']) ? $data['reminder_time'] : null,
            !empty($data['reminder_time_2']) ? $data['reminder_time_2'] : null,
            !empty($data['reminder_time_3']) ? $data['reminder_time_3'] : null,
            $data['reminder_emails'] ?? null,
        ]);
        return (int) $db->lastInsertId();
    }

    public static function update(int $id, int $userId, array $data): bool
    {
        $stmt = Database::getInstance()->prepare(
            'UPDATE notebook_sticky_notes
             SET title = ?, content = ?, theme = ?, reminder_time = ?, reminder_time_2 = ?, reminder_time_3 = ?, reminder_emails = ?
             WHERE id = ? AND user_id = ?'
        );
        return $stmt->execute([
            $data['title'],
            $data['content'],
            $data['theme'] ?? 'sticky-yellow',
            !empty($data['reminder_time']) ? $data['reminder_time'] : null,
            !empty($data['reminder_time_2']) ? $data['reminder_time_2'] : null,
            !empty($data['reminder_time_3']) ? $data['reminder_time_3'] : null,
            $data['reminder_emails'] ?? null,
            $id,
            $userId,
        ]);
    }

    public static function updateReminderTime(int $id, int $userId, ?string $time): bool
    {
        $stmt = Database::getInstance()->prepare(
            'UPDATE notebook_sticky_notes SET reminder_time = ? WHERE id = ? AND user_id = ?'
        );
        return $stmt->execute([!empty($time) ? $time : null, $id, $userId]);
    }

    public static function delete(int $id, int $userId): bool
    {
        $stmt = Database::getInstance()->prepare(
            'DELETE FROM notebook_sticky_notes WHERE id = ? AND user_id = ?'
        );
        return $stmt->execute([$id, $userId]);
    }

    private static function hydrate(array $row): array
    {
        $emails = json_decode((string) ($row['reminder_emails'] ?? '[]'), true);
        $row['id']              = (int) $row['id'];
        $row['user_id']         = (int) $row['user_id'];
        $row['reminder_time']   = $row['reminder_time'] ?? '';
        $row['reminder_time_2'] = $row['reminder_time_2'] ?? '';
        $row['reminder_time_3'] = $row['reminder_time_3'] ?? '';
        $row['reminder_emails'] = is_array($emails) ? $emails : [];
        return $row;
    }
}
