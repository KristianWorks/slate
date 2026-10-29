<?php
/**
 * app/models/Appointment.php
 *
 * Read-side queries used by the dashboard. The full appointment lifecycle
 * (create / cancel / reschedule) will be built in the booking module.
 */

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Appointment
{
    public static function countToday(): int
    {
        $value = Database::getInstance()->fetchColumn(
            "SELECT COUNT(*) FROM appointments
              WHERE DATE(starts_at) = CURDATE()
                AND status IN ('confirmed','pending','completed')",
        );
        return (int) ($value ?: 0);
    }

    public static function countThisWeek(string $startsAt, string $endsAt): int
    {
        $value = Database::getInstance()->fetchColumn(
            "SELECT COUNT(*) FROM appointments
              WHERE starts_at >= :s AND starts_at < :e
                AND status IN ('confirmed','pending','completed')",
            ['s' => $startsAt, 'e' => $endsAt],
        );
        return (int) ($value ?: 0);
    }

    public static function revenueBetween(string $startsAt, string $endsAt): string
    {
        $value = Database::getInstance()->fetchColumn(
            "SELECT COALESCE(SUM(s.price), 0)
               FROM appointments a
               JOIN services s ON s.id = a.service_id
              WHERE a.starts_at >= :s AND a.starts_at < :e
                AND a.status = 'completed'",
            ['s' => $startsAt, 'e' => $endsAt],
        );
        return number_format((float) ($value ?: 0), 2, '.', '');
    }

    /** @return array<string,mixed>|null */
    public static function nextUpcoming(): ?array
    {
        return Database::getInstance()->fetch(
            "SELECT a.id, a.customer_name, a.starts_at, a.ends_at, a.status,
                    s.name AS service_name, s.color AS service_color
               FROM appointments a
               JOIN services s ON s.id = a.service_id
              WHERE a.starts_at > NOW()
                AND a.status IN ('confirmed','pending')
              ORDER BY a.starts_at ASC
              LIMIT 1",
        );
    }

    /**
     * Lookup by id. Joins service for display convenience.
     *
     * @return array<string,mixed>|null
     */
    public static function findById(int $id): ?array
    {
        return Database::getInstance()->fetch(
            "SELECT a.id, a.user_id, a.service_id,
                    a.customer_name, a.customer_email, a.customer_phone,
                    a.starts_at, a.ends_at, a.status, a.notes,
                    a.cancel_token, a.created_at, a.updated_at,
                    s.name              AS service_name,
                    s.duration_minutes  AS service_duration,
                    s.price             AS service_price,
                    s.color             AS service_color
               FROM appointments a
               JOIN services s ON s.id = a.service_id
              WHERE a.id = :id
              LIMIT 1",
            ['id' => $id],
        );
    }

    /**
     * Lookup by cancel_token. Joins service for display convenience.
     *
     * @return array<string,mixed>|null
     */
    public static function findByToken(string $token): ?array
    {
        return Database::getInstance()->fetch(
            "SELECT a.id, a.user_id, a.service_id,
                    a.customer_name, a.customer_email, a.customer_phone,
                    a.starts_at, a.ends_at, a.status, a.notes,
                    a.cancel_token, a.created_at, a.updated_at,
                    s.name              AS service_name,
                    s.duration_minutes  AS service_duration,
                    s.price             AS service_price,
                    s.color             AS service_color
               FROM appointments a
               JOIN services s ON s.id = a.service_id
              WHERE a.cancel_token = :token
              LIMIT 1",
            ['token' => $token],
        );
    }

    public static function cancel(int $id): int
    {
        return Database::getInstance()->update(
            table: 'appointments',
            data:  ['status' => 'cancelled'],
            where: ['id' => $id],
        );
    }

    public static function reschedule(
        int $id,
        int $serviceId,
        string $startsAt,
        string $endsAt,
    ): int {
        return Database::getInstance()->update(
            table: 'appointments',
            data:  [
                'service_id' => $serviceId,
                'starts_at'  => $startsAt,
                'ends_at'    => $endsAt,
                'status'     => 'confirmed',
            ],
            where: ['id' => $id],
        );
    }

    /* ============================================================
     * Admin queries
     * ============================================================ */

    public const VALID_STATUSES = ['pending', 'confirmed', 'cancelled', 'completed', 'no_show'];

    /**
     * Fetch a page of appointments matching the given filter bag.
     *
     * @param  array<string,mixed> $filters {
     *     status?: string ('all' or one of VALID_STATUSES),
     *     service_id?: int (0 = any),
     *     q?: string (name / email / cancel_token suffix),
     *     date_from?: string ('Y-m-d H:i:s'),
     *     date_to?:   string ('Y-m-d H:i:s'),
     *     order?: 'asc'|'desc'  default 'asc'
     * }
     * @return list<array<string,mixed>>
     */
    public static function listFiltered(array $filters, ?int $limit = null, ?int $offset = null): array
    {
        [$where, $params] = self::buildWhere($filters);
        $order = (($filters['order'] ?? 'asc') === 'desc') ? 'DESC' : 'ASC';

        $sql = "SELECT a.id, a.user_id, a.service_id,
                       a.customer_name, a.customer_email, a.customer_phone,
                       a.starts_at, a.ends_at, a.status, a.notes,
                       a.cancel_token, a.created_at, a.updated_at,
                       s.name             AS service_name,
                       s.color            AS service_color,
                       s.duration_minutes AS service_duration,
                       s.price            AS service_price
                  FROM appointments a
                  JOIN services s ON s.id = a.service_id
                 WHERE {$where}
              ORDER BY a.starts_at {$order}, a.id {$order}";

        // PDO's emulate_prepares=false rejects bound LIMIT/OFFSET on older
        // MySQL; cast to int and interpolate (safe — they were already ints).
        if ($limit !== null) {
            $sql .= ' LIMIT ' . (int) $limit;
            if ($offset !== null) {
                $sql .= ' OFFSET ' . (int) $offset;
            }
        }

        return Database::getInstance()->fetchAll($sql, $params);
    }

    /** @param array<string,mixed> $filters */
    public static function countFiltered(array $filters): int
    {
        [$where, $params] = self::buildWhere($filters);
        $value = Database::getInstance()->fetchColumn(
            "SELECT COUNT(*) FROM appointments a WHERE {$where}",
            $params,
        );
        return (int) ($value ?: 0);
    }

    public static function updateStatus(int $id, string $status): int
    {
        if (!in_array($status, self::VALID_STATUSES, true)) {
            throw new \InvalidArgumentException("Invalid status: {$status}");
        }
        return Database::getInstance()->update('appointments', ['status' => $status], ['id' => $id]);
    }

    public static function updateNotes(int $id, string $notes): int
    {
        return Database::getInstance()->update(
            table: 'appointments',
            data:  ['notes' => $notes === '' ? null : $notes],
            where: ['id' => $id],
        );
    }

    /**
     * @param  array<string,mixed> $filters
     * @return array{0:string, 1:array<string,scalar|null>}
     */
    private static function buildWhere(array $filters): array
    {
        $conds  = ['1=1'];
        $params = [];

        // Status
        $status = (string) ($filters['status'] ?? 'all');
        if ($status !== 'all' && in_array($status, self::VALID_STATUSES, true)) {
            $conds[]            = 'a.status = :status';
            $params['status']   = $status;
        }

        // Service
        $serviceId = (int) ($filters['service_id'] ?? 0);
        if ($serviceId > 0) {
            $conds[]                  = 'a.service_id = :service_id';
            $params['service_id']     = $serviceId;
        }

        // Free-text search: name OR email OR cancel_token suffix (the
        // 8-char reference shown on the confirmation page).
        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $conds[] = '(a.customer_name  LIKE :q'
                     . ' OR a.customer_email LIKE :q'
                     . ' OR a.cancel_token   LIKE :qref)';
            $params['q']    = '%' . $q . '%';
            $params['qref'] = '%' . $q;
        }

        // Date range (passed in by the controller, already timezone-aware).
        if (!empty($filters['date_from'])) {
            $conds[]              = 'a.starts_at >= :date_from';
            $params['date_from']  = (string) $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $conds[]              = 'a.starts_at <= :date_to';
            $params['date_to']    = (string) $filters['date_to'];
        }

        return [implode(' AND ', $conds), $params];
    }
}
