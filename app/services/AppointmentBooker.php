<?php
/**
 * app/services/AppointmentBooker.php
 *
 * The one place that knows how to safely insert a confirmed appointment:
 * locks the candidate time range inside a transaction so two writers can
 * never both succeed for an overlapping slot (see BookingController's
 * class doc for the InnoDB gap-lock mechanics). Shared by the booking form
 * (BookingController::store()) and the recurring-series expansion job
 * (app/jobs/ExpandRecurringSeries.php) so this logic exists exactly once.
 */

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

final class AppointmentBooker
{
    /**
     * @param array{
     *   service_id:int, customer_name:string, customer_email:string,
     *   customer_phone?:?string, starts_at:string, ends_at:string,
     *   notes?:?string, series_id?:?int, user_id?:?int, exclude_id?:?int
     * } $data
     * @return array{ok:bool, id:?int, token:?string, conflict:bool}
     */
    public static function book(array $data): array
    {
        $db = Database::getInstance();
        $db->beginTransaction();
        try {
            $sql = "SELECT id FROM appointments
                      WHERE status IN ('pending','confirmed')
                        AND starts_at < :end
                        AND ends_at   > :start";
            $params = ['start' => $data['starts_at'], 'end' => $data['ends_at']];
            if (!empty($data['exclude_id'])) {
                $sql .= ' AND id <> :self';
                $params['self'] = (int) $data['exclude_id'];
            }
            $sql .= ' FOR UPDATE';

            $conflicts = $db->fetchAll($sql, $params);
            if ($conflicts !== []) {
                $db->rollBack();
                return ['ok' => false, 'id' => null, 'token' => null, 'conflict' => true];
            }

            $token = bin2hex(random_bytes(16)); // 32 hex chars

            $id = (int) $db->insert('appointments', [
                'user_id'        => $data['user_id']        ?? null,
                'series_id'      => $data['series_id']      ?? null,
                'service_id'     => $data['service_id'],
                'customer_name'  => $data['customer_name'],
                'customer_email' => $data['customer_email'],
                'customer_phone' => $data['customer_phone'] ?? null,
                'starts_at'      => $data['starts_at'],
                'ends_at'        => $data['ends_at'],
                'status'         => 'confirmed',
                'notes'          => $data['notes'] ?? null,
                'cancel_token'   => $token,
            ]);

            $db->commit();
            return ['ok' => true, 'id' => $id, 'token' => $token, 'conflict' => false];
        } catch (\Throwable $e) {
            if ($db->pdo()->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }
}
