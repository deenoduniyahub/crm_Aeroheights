<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/Session.php';

/**
 * Financial lock state. Unlocking (owner email + password) lives in AccountController::unlockFinancial().
 */
class AuthController {
    /** Terminate the unlocked financial session. */
    public static function logoutFinancial(): array {
        Session::lockFinancial();
        return ['success' => true, 'message' => 'Financial access locked.'];
    }
}
