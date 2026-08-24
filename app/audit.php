<?php

function clientIpAddress()
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
}

function auditLog($action, $entityType, $entityId = null, $before = null, $after = null, $agencyId = null)
{
    if (!tableExists('audit_logs')) {
        return;
    }

    dbExecute(
        'INSERT INTO audit_logs (agency_id, user_id, action, entity_type, entity_id, before_json, after_json, ip_address, user_agent)
         VALUES (:agency_id, :user_id, :action, :entity_type, :entity_id, :before_json, :after_json, :ip_address, :user_agent)',
        [
            'agency_id' => $agencyId,
            'user_id' => currentUserId(),
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId === null ? null : (string) $entityId,
            'before_json' => $before === null ? null : json_encode($before, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'after_json' => $after === null ? null : json_encode($after, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'ip_address' => clientIpAddress(),
            'user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
        ]
    );
}

