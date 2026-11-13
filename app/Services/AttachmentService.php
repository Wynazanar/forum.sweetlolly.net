<?php
namespace App\Services;

use App\Models\Attachment;

class AttachmentService
{
    private Attachment $model;

    /** Расширения, которые нельзя принимать никогда */
    private const BLOCKED_EXT = [
        'php', 'phtml', 'php3', 'php4', 'php5', 'phar', 'exe', 'bat', 'cmd',
        'sh', 'cgi', 'js', 'html', 'htm', 'shtml', 'asp', 'aspx', 'jsp',
    ];

    /** Дефолт, если в вопросе не указан accept */
    private const DEFAULT_EXT = [
        'png', 'jpg', 'jpeg', 'gif', 'webp', 'pdf',
        'zip', 'rar', '7z', 'doc', 'docx', 'txt',
    ];

    private const MAX_DEFAULT = 10 * 1024 * 1024; // 10 MB

    public function __construct()
    {
        $this->model = new Attachment();
    }

    public function find(int $id): ?array
    {
        return $this->model->findById($id);
    }

    public function forTarget(string $type, int $targetId): array
    {
        return $this->model->findByTarget($type, $targetId);
    }

    /**
     * Сохранить один загруженный файл.
     *
     * @param array $file элемент $_FILES[...]
     * @param array $opts target_type, target_id, user_id, field_key?, accept? (string[]|null), max_size?
     * @return array{ok:bool, id?:int, error?:string, meta?:array}
     */
    public function store(array $file, array $opts): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return ['ok' => false, 'error' => 'Файл не выбран'];
        }
        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'error' => 'Ошибка загрузки файла'];
        }

        $original = $file['name'] ?? 'file';
        $tmp = $file['tmp_name'] ?? '';
        $size = (int)($file['size'] ?? 0);

        if ($tmp === '' || !is_uploaded_file($tmp)) {
            return ['ok' => false, 'error' => 'Некорректный upload'];
        }

        $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        if ($ext === '' || in_array($ext, self::BLOCKED_EXT, true)) {
            return ['ok' => false, 'error' => 'Тип файла запрещён'];
        }

        $accept = $opts['accept'] ?? null;
        if (is_array($accept) && $accept !== []) {
            $accept = array_map('strtolower', $accept);
            if (!in_array($ext, $accept, true)) {
                return ['ok' => false, 'error' => 'Допустимы: ' . implode(', ', $accept)];
            }
        } else {
            // если accept не задан — мягкий дефолт (не «всё подряд»)
            if (!in_array($ext, self::DEFAULT_EXT, true)) {
                return ['ok' => false, 'error' => 'Тип файла не из разрешённого списка'];
            }
        }

        $max = (int)($opts['max_size'] ?? self::MAX_DEFAULT);
        if ($size <= 0 || $size > $max) {
            return ['ok' => false, 'error' => 'Размер файла превышает лимит (' . round($max / 1048576, 1) . ' МБ)'];
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmp) ?: ($file['type'] ?? 'application/octet-stream');

        $type = $opts['target_type'] ?? 'other';
        $targetId = (int)($opts['target_id'] ?? 0);
        $userId = (int)($opts['user_id'] ?? 0);

        $subdir = $type . '/' . date('Y/m');
        $root = $this->uploadRoot();
        $dir = $root . '/' . $subdir;
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            return ['ok' => false, 'error' => 'Не удалось создать каталог загрузок'];
        }

        $stored = bin2hex(random_bytes(8)) . '_' . $this->safeBasename($original);
        $full = $dir . '/' . $stored;
        if (!move_uploaded_file($tmp, $full)) {
            return ['ok' => false, 'error' => 'Не удалось сохранить файл'];
        }

        $publicPath = '/uploads/' . $subdir . '/' . $stored;

        $id = $this->model->create([
            'target_type'   => $type,
            'target_id'     => $targetId,
            'field_key'     => $opts['field_key'] ?? null,
            'user_id'       => $userId,
            'original_name' => $original,
            'stored_name'   => $stored,
            'path'          => $publicPath,
            'size'          => $size,
            'mime_type'     => $mime,
        ]);

        return [
            'ok'   => true,
            'id'   => $id,
            'meta' => [
                'attachment_id' => $id,
                'name'          => $original,
                'size'          => $size,
                'mime'          => $mime,
                'path'          => $publicPath,
            ],
        ];
    }

    /**
     * Абсолютный путь к файлу на диске
     */
    public function absolutePath(array $row): string
    {
        $path = $row['path'] ?? '';
        // path вида /uploads/...
        $path = ltrim(str_replace('\\', '/', $path), '/');
        if (str_starts_with($path, 'uploads/')) {
            return $this->projectRoot() . '/public/' . $path;
        }
        return $this->uploadRoot() . '/' . preg_replace('#^uploads/#', '', $path);
    }

    private function uploadRoot(): string
    {
        return $this->projectRoot() . '/public/uploads';
    }

    private function projectRoot(): string
    {
        // app/Services → корень проекта
        return dirname(__DIR__, 2);
    }

    private function safeBasename(string $name): string
    {
        $name = preg_replace('~[^a-zA-Z0-9._-]+~', '_', $name) ?? 'file';
        return mb_substr($name, 0, 120);
    }
}
