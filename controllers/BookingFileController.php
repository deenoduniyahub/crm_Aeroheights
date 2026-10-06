<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../config/Session.php';

/**
 * Booking Ticket/Visa attachment controller.
 * PDF only, max 10 MB, one current file per type per booking.
 */
class BookingFileController {
    private const MAX_BYTES = 10485760;
    private const ALLOWED_TYPES = ['visa', 'passport', 'ticket_1', 'ticket_2'];

    private static function actor(): string {
        return Session::getActor();
    }

    public static function upload(int $bookingId, string $type, array $file): array {
        if ($bookingId <= 0) return ['success' => false, 'message' => 'Invalid booking ID.'];
        $type = strtolower(trim($type));
        if (!in_array($type, self::ALLOWED_TYPES, true)) {
            return ['success' => false, 'message' => 'Attachment type must be Visa, Passport, Ticket 1 or Ticket 2.'];
        }
        $booking = Database::fetchOne("SELECT id FROM master_bookings WHERE id = ? AND deleted_at IS NULL", [$bookingId]);
        if (!$booking) return ['success' => false, 'message' => 'Booking not found.'];

        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => self::uploadError((int)($file['error'] ?? -1))];
        }
        $size = (int)($file['size'] ?? 0);
        if ($size <= 0) return ['success' => false, 'message' => 'The uploaded file is empty.'];
        if ($size > self::MAX_BYTES) return ['success' => false, 'message' => 'File size exceeds the 10 MB limit.'];

        $original = basename((string)($file['name'] ?? 'document.pdf'));
        $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        if ($extension !== 'pdf') return ['success' => false, 'message' => 'Only PDF files are supported for Ticket, Passport and Visa attachments.'];
        if (!is_uploaded_file($file['tmp_name'])) return ['success' => false, 'message' => 'Invalid upload source.'];

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']) ?: '';
        if ($mime !== 'application/pdf') {
            return ['success' => false, 'message' => 'The uploaded file is not a valid PDF.'];
        }

        $storageRoot = __DIR__ . '/../storage/booking_files/' . $bookingId;
        if (!is_dir($storageRoot) && !mkdir($storageRoot, 0750, true) && !is_dir($storageRoot)) {
            return ['success' => false, 'message' => 'Unable to create secure attachment storage.'];
        }

        $storedName = $type . '_' . bin2hex(random_bytes(16)) . '.pdf';
        $target = $storageRoot . '/' . $storedName;
        if (!move_uploaded_file($file['tmp_name'], $target)) {
            return ['success' => false, 'message' => 'Unable to store the uploaded PDF.'];
        }

        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $relativePath = 'storage/booking_files/' . $bookingId . '/' . $storedName;
        $actor = self::actor();

        Database::beginTransaction();
        $oldPathToRemove = null;
        try {
            $old = Database::fetchOne(
                "SELECT * FROM booking_files WHERE booking_id = ? AND attachment_type = ? AND deleted_at IS NULL LIMIT 1",
                [$bookingId, $type]
            );

            if ($old) {
                Database::execute("UPDATE booking_files SET deleted_at = NOW(), updated_by = ?, updated_at = NOW() WHERE id = ?", [$actor, $old['id']]);
                $oldPathToRemove = __DIR__ . '/../' . ltrim((string)$old['storage_path'], '/');
                self::audit($bookingId, 'file_replaced', (int)$old['id'], ['filename' => $old['original_filename']], ['filename' => $original]);
            }

            Database::execute(
                "INSERT INTO booking_files (booking_id, attachment_type, original_filename, stored_filename, storage_path, mime_type, file_size, download_token_hash, created_by, created_at, updated_by, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, NOW())",
                [$bookingId, $type, $original, $storedName, $relativePath, 'application/pdf', $size, $tokenHash, $actor, $actor]
            );
            $fileId = Database::lastInsertId();
            self::audit($bookingId, 'file_uploaded', $fileId, null, ['type' => $type, 'filename' => $original, 'size' => $size]);
            Database::commit();
            if ($oldPathToRemove && is_file($oldPathToRemove)) @unlink($oldPathToRemove);

            return [
                'success' => true,
                'message' => ucfirst($type) . ' PDF uploaded successfully.',
                'file' => [
                    'id' => $fileId,
                    'type' => $type,
                    'filename' => $original,
                    'size' => $size,
                    'url' => 'index.php?api=download_booking_file&id=' . $fileId . '&token=' . $token
                ]
            ];
        } catch (Throwable $e) {
            Database::rollBack();
            @unlink($target);
            return ['success' => false, 'message' => 'Attachment save failed: ' . $e->getMessage()];
        }
    }

    public static function bulkUpload(array $bookingIds, string $type, array $file): array {
        $bookingIds = array_values(array_unique(array_filter(array_map('intval', $bookingIds), static fn(int $id): bool => $id > 0)));
        if (!$bookingIds) return ['success' => false, 'message' => 'Select at least one booking.'];
        $type = strtolower(trim($type));
        if (!in_array($type, self::ALLOWED_TYPES, true)) {
            return ['success' => false, 'message' => 'Attachment type must be Visa, Passport, Ticket 1 or Ticket 2.'];
        }
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => self::uploadError((int)($file['error'] ?? -1))];
        }
        $size = (int)($file['size'] ?? 0);
        if ($size <= 0) return ['success' => false, 'message' => 'The uploaded file is empty.'];
        if ($size > self::MAX_BYTES) return ['success' => false, 'message' => 'File size exceeds the 10 MB limit.'];

        $original = basename((string)($file['name'] ?? 'document.pdf'));
        $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        if ($extension !== 'pdf') return ['success' => false, 'message' => 'Only PDF files are supported for Ticket, Passport and Visa attachments.'];
        if (!is_uploaded_file($file['tmp_name'])) return ['success' => false, 'message' => 'Invalid upload source.'];

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']) ?: '';
        if ($mime !== 'application/pdf') {
            return ['success' => false, 'message' => 'The uploaded file is not a valid PDF.'];
        }

        $sourceBytes = file_get_contents($file['tmp_name']);
        if ($sourceBytes === false) return ['success' => false, 'message' => 'Unable to read the uploaded PDF.'];

        $actor = self::actor();
        $updated = 0; $failed = [];

        foreach ($bookingIds as $bookingId) {
            $booking = Database::fetchOne("SELECT id FROM master_bookings WHERE id = ? AND deleted_at IS NULL", [$bookingId]);
            if (!$booking) { $failed[] = $bookingId; continue; }

            $storageRoot = __DIR__ . '/../storage/booking_files/' . $bookingId;
            if (!is_dir($storageRoot) && !mkdir($storageRoot, 0750, true) && !is_dir($storageRoot)) {
                $failed[] = $bookingId; continue;
            }

            $storedName = $type . '_' . bin2hex(random_bytes(16)) . '.pdf';
            $target = $storageRoot . '/' . $storedName;
            if (file_put_contents($target, $sourceBytes) === false) { $failed[] = $bookingId; continue; }

            $token = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $token);
            $relativePath = 'storage/booking_files/' . $bookingId . '/' . $storedName;

            Database::beginTransaction();
            $oldPathToRemove = null;
            try {
                $old = Database::fetchOne(
                    "SELECT * FROM booking_files WHERE booking_id = ? AND attachment_type = ? AND deleted_at IS NULL LIMIT 1",
                    [$bookingId, $type]
                );

                if ($old) {
                    Database::execute("UPDATE booking_files SET deleted_at = NOW(), updated_by = ?, updated_at = NOW() WHERE id = ?", [$actor, $old['id']]);
                    $oldPathToRemove = __DIR__ . '/../' . ltrim((string)$old['storage_path'], '/');
                    self::audit($bookingId, 'file_replaced', (int)$old['id'], ['filename' => $old['original_filename']], ['filename' => $original]);
                }

                Database::execute(
                    "INSERT INTO booking_files (booking_id, attachment_type, original_filename, stored_filename, storage_path, mime_type, file_size, download_token_hash, created_by, created_at, updated_by, updated_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, NOW())",
                    [$bookingId, $type, $original, $storedName, $relativePath, 'application/pdf', $size, $tokenHash, $actor, $actor]
                );
                $fileId = Database::lastInsertId();
                self::audit($bookingId, 'file_uploaded', $fileId, null, ['type' => $type, 'filename' => $original, 'size' => $size, 'bulk' => true]);
                Database::commit();
                if ($oldPathToRemove && is_file($oldPathToRemove)) @unlink($oldPathToRemove);
                $updated++;
            } catch (Throwable $e) {
                Database::rollBack();
                @unlink($target);
                $failed[] = $bookingId;
            }
        }

        if ($updated === 0) {
            return ['success' => false, 'message' => 'Unable to attach the file to any selected booking.'];
        }
        $label = $type === 'ticket_1' ? 'Ticket 1 (Arrival)' : ($type === 'ticket_2' ? 'Ticket 2 (Return)' : ucfirst($type));
        return [
            'success' => true,
            'message' => $label . ' PDF attached to ' . $updated . ' booking(s)' . ($failed ? ' (' . count($failed) . ' failed)' : '') . '.',
            'updated' => $updated,
            'failed' => $failed
        ];
    }

    public static function remove(int $fileId): array {
        if ($fileId <= 0) return ['success' => false, 'message' => 'Invalid attachment ID.'];
        $file = Database::fetchOne("SELECT * FROM booking_files WHERE id = ? AND deleted_at IS NULL", [$fileId]);
        if (!$file) return ['success' => false, 'message' => 'Attachment not found.'];
        $actor = self::actor();

        Database::beginTransaction();
        try {
            Database::execute("UPDATE booking_files SET deleted_at = NOW(), updated_by = ?, updated_at = NOW() WHERE id = ?", [$actor, $fileId]);
            self::audit((int)$file['booking_id'], 'file_removed', $fileId, ['filename' => $file['original_filename']], null);
            Database::commit();
            $path = __DIR__ . '/../' . ltrim((string)$file['storage_path'], '/');
            if (is_file($path)) @unlink($path);
            return ['success' => true, 'message' => 'Attachment removed successfully.'];
        } catch (Throwable $e) {
            Database::rollBack();
            return ['success' => false, 'message' => 'Unable to remove attachment: ' . $e->getMessage()];
        }
    }

    public static function getCurrentWithUrls(int $bookingId): array {
        $files = Database::fetchAll(
            "SELECT id, booking_id, attachment_type, original_filename, file_size, created_at, updated_at
             FROM booking_files WHERE booking_id = ? AND deleted_at IS NULL ORDER BY attachment_type",
            [$bookingId]
        );
        foreach ($files as &$file) {
            $token = bin2hex(random_bytes(32));
            Database::execute("UPDATE booking_files SET download_token_hash = ?, updated_at = updated_at WHERE id = ? AND deleted_at IS NULL", [hash('sha256', $token), (int)$file['id']]);
            $file['download_url'] = 'index.php?api=download_booking_file&id=' . (int)$file['id'] . '&token=' . $token;
        }
        return $files;
    }

    public static function getCurrent(int $bookingId): array {
        return Database::fetchAll(
            "SELECT id, booking_id, attachment_type, original_filename, file_size, created_at, updated_at
             FROM booking_files WHERE booking_id = ? AND deleted_at IS NULL ORDER BY attachment_type",
            [$bookingId]
        );
    }

    public static function download(int $fileId, string $token): void {
        $hash = hash('sha256', trim($token));
        $file = $token !== ''
            ? Database::fetchOne("SELECT * FROM booking_files WHERE id = ? AND download_token_hash = ? AND deleted_at IS NULL", [$fileId, $hash])
            : null;
        if (!$file) { http_response_code(404); exit('File not found or link expired.'); }
        $path = __DIR__ . '/../' . ltrim((string)$file['storage_path'], '/');
        if (!is_file($path)) { http_response_code(404); exit('Stored file not found.'); }

        header('Content-Type: application/pdf');
        header('Content-Length: ' . filesize($path));
        header('Content-Disposition: inline; filename="' . str_replace('"', '', basename((string)$file['original_filename'])) . '"');
        header('X-Content-Type-Options: nosniff');
        readfile($path);
        exit;
    }

    private static function audit(int $bookingId, string $action, int $entityId, ?array $old, ?array $new): void {
        Database::execute(
            "INSERT INTO audit_logs (entity_type, entity_id, action, old_values, new_values, created_by, created_at)
             VALUES ('booking_file', ?, ?, ?, ?, ?, NOW())",
            [$entityId, $action, $old ? json_encode($old, JSON_UNESCAPED_UNICODE) : null, $new ? json_encode($new, JSON_UNESCAPED_UNICODE) : null, self::actor()]
        );
    }

    private static function uploadError(int $code): string {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'File exceeds the server upload size limit.',
            UPLOAD_ERR_PARTIAL => 'The file upload was interrupted. Please try again.',
            UPLOAD_ERR_NO_FILE => 'Please select a PDF file.',
            default => 'File upload failed.'
        };
    }
}
