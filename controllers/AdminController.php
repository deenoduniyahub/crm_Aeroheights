<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/Database.php';

/**
 * Administrative Control Controller
 * Handles CRUD for B2B Agents, Suppliers, Bank Accounts, Saudi Helplines, and Agency Configuration.
 */
class AdminController {

    // =========================================================================
    // 1. Agent Management (B2B Clients)
    // =========================================================================
    public static function getAgents(): array {
        return Database::fetchAll("SELECT * FROM agents ORDER BY name ASC");
    }

    public static function saveAgent(array $data): array {
        $name    = trim($data['name'] ?? '');
        $company = trim($data['company_name'] ?? '');
        $phone   = trim($data['phone'] ?? '');
        $city    = trim($data['city'] ?? 'Lahore');
        $address = trim($data['address'] ?? '');
        $id      = !empty($data['id']) ? (int)$data['id'] : null;

        if (empty($name)) {
            return ['success' => false, 'message' => 'Agent name is required.'];
        }
        if (empty($phone)) {
            return ['success' => false, 'message' => 'Contact phone number is required.'];
        }

        if ($id) {
            $sql = "UPDATE agents SET name = ?, company_name = ?, phone = ?, city = ?, address = ? WHERE id = ?";
            Database::execute($sql, [$name, $company, $phone, $city, $address, $id]);
            return ['success' => true, 'message' => 'Agent details updated successfully.', 'agent_id' => $id];
        } else {
            $sql = "INSERT INTO agents (name, company_name, phone, city, address) VALUES (?, ?, ?, ?, ?)";
            Database::execute($sql, [$name, $company, $phone, $city, $address]);
            return ['success' => true, 'message' => 'New B2B Agent registered successfully.', 'agent_id' => Database::lastInsertId()];
        }
    }

    public static function deleteAgent(int $id): array {
        if ($id <= 0) {
            return ['success' => false, 'message' => 'Invalid Agent ID.'];
        }
        Database::execute("DELETE FROM agents WHERE id = ?", [$id]);
        return ['success' => true, 'message' => 'Agent removed successfully.'];
    }

    // =========================================================================
    // 2. Vendor Management (Suppliers)
    // =========================================================================
    public static function getVendors(): array {
        return Database::fetchAll("SELECT * FROM vendors ORDER BY name ASC");
    }

    public static function saveVendor(array $data): array {
        $name    = trim($data['name'] ?? '');
        $company = trim($data['company_name'] ?? '');
        $service = trim($data['service_type'] ?? 'Visas & Hotel BRN');
        $phone   = trim($data['phone'] ?? '');
        $country = trim($data['country'] ?? 'Saudi Arabia');
        $id      = !empty($data['id']) ? (int)$data['id'] : null;

        if (empty($name)) {
            return ['success' => false, 'message' => 'Vendor name is required.'];
        }
        if (empty($phone)) {
            return ['success' => false, 'message' => 'Vendor contact phone is required.'];
        }

        if ($id) {
            $sql = "UPDATE vendors SET name = ?, company_name = ?, service_type = ?, phone = ?, country = ? WHERE id = ?";
            Database::execute($sql, [$name, $company, $service, $phone, $country, $id]);
            return ['success' => true, 'message' => 'Supplier record updated successfully.', 'vendor_id' => $id];
        } else {
            $sql = "INSERT INTO vendors (name, company_name, service_type, phone, country) VALUES (?, ?, ?, ?, ?)";
            Database::execute($sql, [$name, $company, $service, $phone, $country]);
            return ['success' => true, 'message' => 'New Supplier registered successfully.', 'vendor_id' => Database::lastInsertId()];
        }
    }

    public static function deleteVendor(int $id): array {
        if ($id <= 0) {
            return ['success' => false, 'message' => 'Invalid Vendor ID.'];
        }
        Database::execute("DELETE FROM vendors WHERE id = ?", [$id]);
        return ['success' => true, 'message' => 'Vendor removed successfully.'];
    }

    // =========================================================================
    // 3. Bank Account Management
    // =========================================================================
    public static function getBankAccounts(): array {
        return Database::fetchAll("SELECT * FROM bank_accounts WHERE is_active = 1 ORDER BY bank_name ASC");
    }

    public static function saveBankAccount(array $data): array {
        $bankName   = trim($data['bank_name'] ?? '');
        $title      = trim($data['account_title'] ?? '');
        $accNumber  = trim($data['account_number'] ?? '');
        $branchCode = trim($data['branch_code'] ?? '');
        $id         = !empty($data['id']) ? (int)$data['id'] : null;

        if (empty($bankName) || empty($accNumber)) {
            return ['success' => false, 'message' => 'Bank name and Account number are required.'];
        }

        if ($id) {
            $sql = "UPDATE bank_accounts SET bank_name = ?, account_title = ?, account_number = ?, branch_code = ? WHERE id = ?";
            Database::execute($sql, [$bankName, $title, $accNumber, $branchCode, $id]);
            return ['success' => true, 'message' => 'Bank account updated successfully.'];
        } else {
            $sql = "INSERT INTO bank_accounts (bank_name, account_title, account_number, branch_code) VALUES (?, ?, ?, ?)";
            Database::execute($sql, [$bankName, $title, $accNumber, $branchCode]);
            return ['success' => true, 'message' => 'Bank account created successfully.', 'account_id' => Database::lastInsertId()];
        }
    }

    // =========================================================================
    // 4. Global Agency Settings & Security PIN
    // =========================================================================
    public static function getSettings(): array {
        $raw = Database::fetchAll("SELECT setting_key, setting_value FROM system_settings");
        $settings = [];
        foreach ($raw as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        return $settings;
    }

    public static function updateSettings(array $settings): array {
        Database::beginTransaction();
        try {
            // Each placeholder used once: native prepares (EMULATE_PREPARES off) reject a repeated named parameter.
            $stmt = Database::getConnection()->prepare(
                "INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
            );

            foreach ($settings as $key => $val) {
                if (in_array($key, ['financial_pin', 'raw_financial_pin'], true)) continue; // replaced by the owner email + password lock
                $stmt->execute([(string)$key, (string)$val]);
            }

            Database::commit();
            return ['success' => true, 'message' => 'System settings updated successfully.'];
        } catch (Exception $e) {
            Database::rollBack();
            return ['success' => false, 'message' => 'Failed to update settings: ' . $e->getMessage()];
        }
    }
}

