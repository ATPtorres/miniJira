<?php
/** Persistencia de proyectos y sus grupos de estudiantes. */

declare(strict_types=1);

final class Project
{
    public static function find(int $id): ?array
    {
        $stmt = db()->prepare(
            'SELECT p.*, CONCAT(u.first_name, " ", u.last_name) AS teacher_name,
                    COUNT(DISTINCT ps.student_id) AS student_count
             FROM projects p JOIN users u ON u.id = p.responsible_teacher_id
             LEFT JOIN project_students ps ON ps.project_id = p.id
             WHERE p.id = ? GROUP BY p.id LIMIT 1'
        );
        $stmt->execute([$id]);
        $project = $stmt->fetch();
        return $project === false ? null : $project;
    }

    public static function accessibleTo(int $userId, bool $allProjects = false): array
    {
        $condition = $allProjects
            ? 'p.deleted_at IS NULL'
            : '(p.responsible_teacher_id = ? OR EXISTS (SELECT 1 FROM project_students own_ps WHERE own_ps.project_id = p.id AND own_ps.student_id = ?)) AND p.deleted_at IS NULL';
        $sql = 'SELECT p.*, CONCAT(u.first_name, " ", u.last_name) AS teacher_name,
                       COUNT(DISTINCT ps.student_id) AS student_count,
                       COUNT(DISTINCT CASE WHEN t.status = "todo" THEN t.id END) AS todo_count,
                       COUNT(DISTINCT CASE WHEN t.status = "doing" THEN t.id END) AS doing_count,
                       COUNT(DISTINCT CASE WHEN t.status = "review" THEN t.id END) AS review_count,
                       COUNT(DISTINCT CASE WHEN t.status = "done" THEN t.id END) AS done_count
                FROM projects p
                JOIN users u ON u.id = p.responsible_teacher_id
                LEFT JOIN project_students ps ON ps.project_id = p.id
                LEFT JOIN project_tasks t ON t.project_id = p.id AND t.deleted_at IS NULL
                WHERE ' . $condition . '
                GROUP BY p.id ORDER BY p.end_date ASC, p.created_at DESC';
        $stmt = db()->prepare($sql);
        $stmt->execute($allProjects ? [] : [$userId, $userId]);
        return $stmt->fetchAll();
    }

    public static function create(array $data, array $studentIds): int
    {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO projects (title, description, objectives, start_date, end_date, responsible_teacher_id, created_by_id)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $data['title'], $data['description'], $data['objectives'], $data['start_date'], $data['end_date'],
                $data['responsible_teacher_id'], $data['created_by_id'],
            ]);
            $id = (int) $pdo->lastInsertId();
            self::syncStudents($id, $studentIds, $pdo);
            $pdo->commit();
            return $id;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function update(int $projectId, array $data, array $studentIds): void
    {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'UPDATE projects SET title = ?, description = ?, objectives = ?, start_date = ?, end_date = ?, responsible_teacher_id = ?
                 WHERE id = ? AND deleted_at IS NULL'
            );
            $stmt->execute([$data['title'], $data['description'], $data['objectives'], $data['start_date'], $data['end_date'], $data['responsible_teacher_id'], $projectId]);
            self::syncStudents($projectId, $studentIds, $pdo);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function softDelete(int $projectId): void
    {
        $stmt = db()->prepare('UPDATE projects SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL');
        $stmt->execute([$projectId]);
    }

    /** @return array<int, array{id:int,first_name:string,last_name:string}> */
    public static function students(int $projectId): array
    {
        $stmt = db()->prepare(
            'SELECT u.id, u.first_name, u.last_name FROM project_students ps
             JOIN users u ON u.id = ps.student_id WHERE ps.project_id = ? ORDER BY u.first_name, u.last_name'
        );
        $stmt->execute([$projectId]);
        return $stmt->fetchAll();
    }

    /** @return int[] */
    public static function studentIds(int $projectId): array
    {
        return array_map(static fn (array $row): int => (int) $row['id'], self::students($projectId));
    }

    public static function userCanAccess(int $projectId, int $userId, bool $allProjects = false): bool
    {
        if ($allProjects) {
            $stmt = db()->prepare('SELECT COUNT(*) FROM projects WHERE id = ? AND deleted_at IS NULL');
            $stmt->execute([$projectId]);
            return (int) $stmt->fetchColumn() === 1;
        }
        $stmt = db()->prepare(
            'SELECT COUNT(*) FROM projects p LEFT JOIN project_students ps ON ps.project_id = p.id
             WHERE p.id = ? AND p.deleted_at IS NULL AND (p.responsible_teacher_id = ? OR ps.student_id = ?)'
        );
        $stmt->execute([$projectId, $userId, $userId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public static function userCanManage(int $projectId, int $userId, bool $allProjects = false): bool
    {
        if ($allProjects) {
            return self::userCanAccess($projectId, $userId, true);
        }
        $stmt = db()->prepare('SELECT COUNT(*) FROM projects WHERE id = ? AND responsible_teacher_id = ? AND deleted_at IS NULL');
        $stmt->execute([$projectId, $userId]);
        return (int) $stmt->fetchColumn() === 1;
    }

    public static function summaryFor(int $userId, bool $allProjects = false): array
    {
        $projects = self::accessibleTo($userId, $allProjects);
        $summary = ['projects' => count($projects), 'todo' => 0, 'doing' => 0, 'review' => 0, 'done' => 0];
        foreach ($projects as $project) {
            foreach (['todo', 'doing', 'review', 'done'] as $status) {
                $summary[$status] += (int) ($project[$status . '_count'] ?? 0);
            }
        }
        return $summary;
    }

    private static function syncStudents(int $projectId, array $studentIds, PDO $pdo): void
    {
        $pdo->prepare('DELETE FROM project_students WHERE project_id = ?')->execute([$projectId]);
        $stmt = $pdo->prepare('INSERT INTO project_students (project_id, student_id) VALUES (?, ?)');
        foreach (array_unique($studentIds) as $studentId) {
            $stmt->execute([$projectId, $studentId]);
        }
    }
}
