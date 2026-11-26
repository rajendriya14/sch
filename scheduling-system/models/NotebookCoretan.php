<?php

namespace Models;

use Config\Database;
use PDO;

class NotebookCoretan
{
    public static function allByUser(int $userId): array
    {
        $stmt = Database::getInstance()->prepare(
            'SELECT * FROM notebook_coretan WHERE user_id = ? ORDER BY is_pinned DESC, updated_at DESC, id DESC'
        );
        $stmt->execute([$userId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$r) {
            $r['id'] = (int) $r['id'];
            $r['user_id'] = (int) $r['user_id'];
            $r['is_pinned'] = (bool) $r['is_pinned'];
        }
        return $rows;
    }

    public static function findByUser(int $id, int $userId): ?array
    {
        $stmt = Database::getInstance()->prepare(
            'SELECT * FROM notebook_coretan WHERE id = ? AND user_id = ? LIMIT 1'
        );
        $stmt->execute([$id, $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        $row['id'] = (int) $row['id'];
        $row['user_id'] = (int) $row['user_id'];
        $row['is_pinned'] = (bool) $row['is_pinned'];
        return $row;
    }

    public static function create(array $data): int
    {
        $db = Database::getInstance();
        $stmt = $db->prepare(
            'INSERT INTO notebook_coretan (user_id, title, content, paper_style, font_family, is_pinned)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            (int) $data['user_id'],
            (string) ($data['title'] ?? 'Untitled'),
            (string) ($data['content'] ?? ''),
            (string) ($data['paper_style'] ?? 'plain'),
            (string) ($data['font_family'] ?? 'sans'),
            !empty($data['is_pinned']) ? 1 : 0,
        ]);
        return (int) $db->lastInsertId();
    }

    public static function update(int $id, int $userId, array $data): bool
    {
        $stmt = Database::getInstance()->prepare(
            'UPDATE notebook_coretan
             SET title = ?, content = ?, paper_style = ?, font_family = ?, is_pinned = ?
             WHERE id = ? AND user_id = ?'
        );
        return $stmt->execute([
            (string) ($data['title'] ?? 'Untitled'),
            (string) ($data['content'] ?? ''),
            (string) ($data['paper_style'] ?? 'plain'),
            (string) ($data['font_family'] ?? 'sans'),
            !empty($data['is_pinned']) ? 1 : 0,
            $id,
            $userId,
        ]);
    }

    public static function delete(int $id, int $userId): bool
    {
        $stmt = Database::getInstance()->prepare(
            'DELETE FROM notebook_coretan WHERE id = ? AND user_id = ?'
        );
        return $stmt->execute([$id, $userId]);
    }
}
