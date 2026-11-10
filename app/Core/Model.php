<?php
namespace Core;

class Model
{
    protected static $link;

    public function __construct()
    {
        if (!self::$link) {
            self::$link = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            mysqli_set_charset(self::$link, 'utf8mb4');
        }
    }

    protected function findOne(string $sql, array $params = []): ?array
    {
        $stmt = $this->query($sql, $params);
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    protected function findMany(string $sql, array $params = []): array
    {
        $stmt = $this->query($sql, $params);
        $result = $stmt->get_result();
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        $stmt->close();
        return $data;
    }

    /**
     * Для SELECT — возвращает mysqli_stmt
     */
    protected function query(string $sql, array $params = []): \mysqli_stmt
    {
        return $this->prepareAndExecute($sql, $params);
    }

    /**
     * Для INSERT / UPDATE / DELETE
     */
    protected function execute(string $sql, array $params = []): bool
    {
        $stmt = $this->prepareAndExecute($sql, $params);
        $stmt->close();
        return true;
    }

    protected function lastInsertId(): int
    {
        return (int) self::$link->insert_id;
    }

    protected function affectedRows(): int
    {
        return (int) self::$link->affected_rows;
    }

    private function prepareAndExecute(string $sql, array $params): \mysqli_stmt
    {
        $stmt = self::$link->prepare($sql);
        if (!$stmt) {
            throw new \RuntimeException(mysqli_error(self::$link));
        }

        if ($params) {
            $types = '';
            foreach ($params as $param) {
                if (is_int($param)) {
                    $types .= 'i';
                } elseif (is_float($param)) {
                    $types .= 'd';
                } else {
                    $types .= 's';
                }
            }
            $stmt->bind_param($types, ...$params);
        }

        if (!$stmt->execute()) {
            throw new \RuntimeException($stmt->error);
        }

        return $stmt;
    }
}