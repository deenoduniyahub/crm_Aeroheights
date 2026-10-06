<?php
// Copy to config/env.php (never commit env.php). Values differ between local XAMPP and live.
return [
    'db_host' => 'localhost',
    'db_port' => 3306,
    'db_name' => 'aeroheights_crm',
    'db_user' => 'root',
    'db_pass' => '',

    // Outgoing mail for password-reset OTPs. Leave smtp_host empty to use PHP mail().
    'mail_from'      => 'no-reply@aeroheightstravels.com',
    'mail_from_name' => 'Aeroheights CRM',
    'smtp_host'      => '',   // e.g. smtp.hostinger.com or smtp.gmail.com
    'smtp_port'      => 465,  // 465 = SSL, 587 = STARTTLS
    'smtp_user'      => '',
    'smtp_pass'      => '',
];
