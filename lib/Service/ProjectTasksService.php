<?php

declare(strict_types=1);

namespace OCA\SuperAdminPage\Service;

use OCP\IDBConnection;

class ProjectTasksService {

    private IDBConnection $db;

    public function __construct(IDBConnection $db) {
        $this->db = $db;
    }

    /**
     * Returns the per-project task list for the superadmin drill-down,
     * or null if the project does not exist.
     */
    public function getTasksForProject(int $projectId): ?array {
        $project = $this->fetchProject($projectId);
        if ($project === null) {
            return null;
        }

        $boardId = (int)$project['board_id'];
        $taskRows = $this->fetchTaskRowsForBoard($boardId);
        $labelsByCard = $this->fetchCardLabels($boardId);
        $assigneesByCard = $this->fetchCardAssignees($boardId);

        $tasks = [];
        foreach ($taskRows as $row) {
            $cardId = (int)$row['task_id'];
            $tasks[] = [
                'id'        => $cardId,
                'title'     => $row['task_title'],
                'stack'     => $row['stack_title'],
                'status'    => $row['task_status'],
                'due'       => $row['duedate'],
                'createdAt' => $row['card_created_at'],
                'dueBucket' => $row['due_bucket'],
                'labels'    => $labelsByCard[$cardId] ?? [],
                'assignees' => $assigneesByCard[$cardId] ?? [],
            ];
        }

        return [
            'projectId'      => (int)$project['id'],
            'projectName'    => $project['name'],
            'organizationId' => (int)$project['organization_id'],
            'tasks'          => $tasks,
        ];
    }

    private function fetchProject(int $projectId): ?array {
        $sql = "
            SELECT cp.id, cp.name, cp.organization_id, cp.board_id
            FROM *PREFIX*custom_projects cp
            INNER JOIN *PREFIX*deck_boards b
                ON b.id = CAST(cp.board_id AS UNSIGNED)
                AND b.deleted_at = 0
            WHERE cp.id = ?
            LIMIT 1
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$projectId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Raw task rows for a single board, with computed status / due bucket.
     * Excludes soft-deleted cards. A card is "done" if its stack is the
     * 'Approved/Done' stack OR the card has a non-null `done` timestamp.
     */
    private function fetchTaskRowsForBoard(int $boardId): array {
        $sql = "
            SELECT
                c.id            AS task_id,
                c.title         AS task_title,
                s.title         AS stack_title,
                c.duedate,
                c.archived,
                c.created_at    AS card_created_at,
                CASE
                    WHEN c.archived = 1                                       THEN 'archived'
                    WHEN s.title = 'Approved/Done' OR c.done IS NOT NULL      THEN 'done'
                    ELSE 'open'
                END AS task_status,
                CASE
                    WHEN c.duedate IS NULL                                     THEN 'nodue'
                    WHEN DATE(c.duedate) < CURDATE()                           THEN 'overdue'
                    WHEN DATE(c.duedate) = CURDATE()                           THEN 'today'
                    WHEN DATE(c.duedate) = DATE_ADD(CURDATE(), INTERVAL 1 DAY) THEN 'tomorrow'
                    WHEN DATE(c.duedate) <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 'nextSevenDays'
                    ELSE 'later'
                END AS due_bucket
            FROM *PREFIX*deck_cards c
            JOIN *PREFIX*deck_stacks s ON s.id = c.stack_id
            JOIN *PREFIX*deck_boards b ON b.id = s.board_id
            WHERE b.id = ?
              AND b.deleted_at = 0
              AND c.deleted_at = 0
            ORDER BY c.id
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(1, $boardId, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** card_id => [label_title, ...] */
    private function fetchCardLabels(int $boardId): array {
        $sql = "
            SELECT al.card_id, l.title AS label_title
            FROM *PREFIX*deck_assigned_labels al
            JOIN *PREFIX*deck_labels l ON l.id = al.label_id
            JOIN *PREFIX*deck_cards c  ON c.id = al.card_id
            JOIN *PREFIX*deck_stacks s ON s.id = c.stack_id
            WHERE s.board_id = ?
              AND c.deleted_at = 0
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(1, $boardId, \PDO::PARAM_INT);
        $stmt->execute();

        $map = [];
        foreach ($stmt->fetchAll() as $row) {
            $map[(int)$row['card_id']][] = $row['label_title'];
        }
        return $map;
    }

    /** card_id => [participant_uid, ...] — type=0 restricts to user assignees */
    private function fetchCardAssignees(int $boardId): array {
        $sql = "
            SELECT au.card_id, au.participant
            FROM *PREFIX*deck_assigned_users au
            JOIN *PREFIX*deck_cards c  ON c.id = au.card_id
            JOIN *PREFIX*deck_stacks s ON s.id = c.stack_id
            WHERE s.board_id = ?
              AND au.type = 0
              AND c.deleted_at = 0
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(1, $boardId, \PDO::PARAM_INT);
        $stmt->execute();

        $map = [];
        foreach ($stmt->fetchAll() as $row) {
            $map[(int)$row['card_id']][] = $row['participant'];
        }
        return $map;
    }
}
