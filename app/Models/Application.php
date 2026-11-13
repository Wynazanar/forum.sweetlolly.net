<?php
namespace App\Models;

use Core\Model;

class Application extends Model
{
    /**
     * Список заявок с фильтрами
     */
    public function findAll(array $filters = []): array
    {
        $where  = [];
        $params = [];

        if (!empty($filters['form_id'])) {
            $where[]  = 'a.form_id = ?';
            $params[] = (int)$filters['form_id'];
        }

        if (!empty($filters['status'])) {
            $where[]  = 'a.status = ?';
            $params[] = $filters['status'];
        }

        if (!empty($filters['user_id'])) {
            $where[]  = 'a.user_id = ?';
            $params[] = (int)$filters['user_id'];
        }

        if (!empty($filters['q'])) {
            $where[]  = '(u.username LIKE ? OR f.name LIKE ?)';
            $q = '%' . $filters['q'] . '%';
            $params[] = $q;
            $params[] = $q;
        }

        $sql = "
            SELECT
                a.id, a.form_id, a.user_id, a.answers, a.form_snapshot,
                a.status, a.reviewed_by, a.reviewed_at, a.admin_comment,
                a.created_at, a.updated_at,
                f.name AS form_name, f.slug AS form_slug, f.application_type,
                u.username, u.avatar,
                r.username AS reviewer_name
            FROM applications a
            INNER JOIN application_forms f ON f.id = a.form_id
            INNER JOIN users u ON u.id = a.user_id
            LEFT JOIN users r ON r.id = a.reviewed_by
        ";

        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY a.created_at DESC';

        if (!empty($filters['limit'])) {
            $sql .= ' LIMIT ' . (int)$filters['limit'];
            if (!empty($filters['offset'])) {
                $sql .= ' OFFSET ' . (int)$filters['offset'];
            }
        }

        $rows = $this->findMany($sql, $params);

        foreach ($rows as &$row) {
            $row = $this->decodeJsonFields($row);
        }
        unset($row);

        return $rows;
    }

    public function findById(int $id): ?array
    {
        $row = $this->findOne(
            "SELECT
                a.id, a.form_id, a.user_id, a.answers, a.form_snapshot,
                a.status, a.reviewed_by, a.reviewed_at, a.admin_comment,
                a.created_at, a.updated_at,
                f.name AS form_name, f.slug AS form_slug, f.application_type,
                u.username, u.avatar, u.email,
                r.username AS reviewer_name
             FROM applications a
             INNER JOIN application_forms f ON f.id = a.form_id
             INNER JOIN users u ON u.id = a.user_id
             LEFT JOIN users r ON r.id = a.reviewed_by
             WHERE a.id = ?",
            [$id]
        );

        return $row ? $this->decodeJsonFields($row) : null;
    }

    public function countByStatus(?int $formId = null): array
    {
        $sql = "
            SELECT status, COUNT(*) AS cnt
            FROM applications
        ";
        $params = [];

        if ($formId) {
            $sql .= ' WHERE form_id = ?';
            $params[] = $formId;
        }

        $sql .= ' GROUP BY status';

        $rows = $this->findMany($sql, $params);
        $result = [
            'pending'    => 0,
            'needs_info' => 0,
            'accepted'   => 0,
            'rejected'   => 0,
            'withdrawn'  => 0,
            'rejected'  => 0,
            'cancelled' => 0,
            'total'     => 0,
        ];

        foreach ($rows as $row) {
            $status = $row['status'];
            $cnt = (int)$row['cnt'];
            if (isset($result[$status])) {
                $result[$status] = $cnt;
            }
            $result['total'] += $cnt;
        }

        return $result;
    }

    public function create(array $data): int
    {
        $this->execute(
            "INSERT INTO applications
                (form_id, user_id, answers, form_snapshot, status)
             VALUES (?, ?, ?, ?, ?)",
            [
                (int)$data['form_id'],
                (int)$data['user_id'],
                $this->encodeJson($data['answers'] ?? []),
                $this->encodeJson($data['form_snapshot'] ?? []),
                $data['status'] ?? 'pending',
            ]
        );

        return (int)$this->lastInsertId();
    }

    /**
     * Смена статуса (принятие / отклонение)
     */
    public function updateStatus(int $id, array $data): bool
    {
        $this->execute(
            "UPDATE applications SET
                status = ?,
                reviewed_by = ?,
                reviewed_at = ?,
                admin_comment = ?
             WHERE id = ?",
            [
                $data['status'],
                $data['reviewed_by'] ?? null,
                $data['reviewed_at'] ?? date('Y-m-d H:i:s'),
                $data['admin_comment'] ?? null,
                $id,
            ]
        );

        return $this->affectedRows() >= 0;
    }

    /**
     * Есть ли уже активная заявка от пользователя по этой форме
     */
    public function hasPending(int $formId, int $userId): bool
    {
        $row = $this->findOne(
            "SELECT id FROM applications
             WHERE form_id = ? AND user_id = ? AND status IN ('pending', 'needs_info')
             LIMIT 1",
            [$formId, $userId]
        );

        return $row !== null;
    }

    private function encodeJson($value): string
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $value;
            }
        }
        return json_encode($value ?? [], JSON_UNESCAPED_UNICODE);
    }

    private function decodeJsonFields(array $row): array
    {
        foreach (['answers', 'form_snapshot'] as $key) {
            if (isset($row[$key]) && is_string($row[$key])) {
                $row[$key] = json_decode($row[$key], true) ?: [];
            }
        }
        return $row;
    }

    public function findByUser(int $userId): array
    {
        $rows = $this->findMany(
            "SELECT
                a.id, a.form_id, a.user_id, a.answers, a.form_snapshot,
                a.status, a.reviewed_by, a.reviewed_at, a.admin_comment,
                a.created_at, a.updated_at,
                f.name AS form_name,
                f.application_type,
                f.slug AS form_slug
             FROM applications a
             JOIN application_forms f ON f.id = a.form_id
             WHERE a.user_id = ?
             ORDER BY a.created_at DESC",
            [$userId]
        );

        foreach ($rows as &$row) {
            $row = $this->decodeJsonFields($row);
        }
        unset($row);

        return $rows;
    }

    public function setStatus(int $id, string $status, array $extra = []): bool
    {
        $fields = ['status = ?'];
        $params = [$status];

        if (array_key_exists('reviewed_by', $extra)) {
            $fields[] = 'reviewed_by = ?';
            $params[] = $extra['reviewed_by'];
        }
        if (array_key_exists('reviewed_at', $extra)) {
            $fields[] = 'reviewed_at = ?';
            $params[] = $extra['reviewed_at'];
        }
        if (array_key_exists('admin_comment', $extra)) {
            $fields[] = 'admin_comment = ?';
            $params[] = $extra['admin_comment'];
        }

        $params[] = $id;

        $this->execute(
            "UPDATE applications SET " . implode(', ', $fields) . " WHERE id = ?",
            $params
        );

        return $this->affectedRows() >= 0;
    }

    public function updateAnswers(int $id, array $answers): bool
    {
        $this->execute(
            "UPDATE applications SET answers = ? WHERE id = ?",
            [json_encode($answers, JSON_UNESCAPED_UNICODE), $id]
        );
        return $this->affectedRows() >= 0;
    }
}
