<?php
// modules/audit/audit.php

class AuditLog {
    private $db;

    public function __construct($db_conn) {
        $this->db = $db_conn;
    }

    public function logAction($user_id, $action, $module, $tenant_id = null) {
        $ip = $_SERVER['REMOTE_ADDR'];
        
        // Agar tenant_id 0 ya khali ho, to usay null kar dein takay foreign key error na aaye
        if (!$tenant_id || $tenant_id == 0) {
            $tenant_id = null;
        }

        $sql = "INSERT INTO audit_logs (tenant_id, user_id, action, module, ip_address) 
                VALUES (?, ?, ?, ?, ?)";
        
        $stmt = $this->db->prepare($sql);
        
        // "issss" ka matlab hai: i (int ya null), i (int), s (string), s (string), s (string)
        $stmt->bind_param("iisss", $tenant_id, $user_id, $action, $module, $ip);
        
        return $stmt->execute();
    }
}
?>