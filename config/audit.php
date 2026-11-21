<?php

/**
 * Configuración de auditoría para cumplimiento RGPD/LOPD.
 *
 * AccountDeletionService escribe aquí el log de borrados de cuenta.
 * En producción: define un canal dedicado (archivo separado o syslog)
 * para que los registros de auditoría sean inmutables y retenidos el
 * plazo legal aplicable (LOPDGDD en España: hasta 2 años para logs de
 * seguridad; el derecho al olvido no exime la conservación de evidencias
 * de cumplimiento).
 */
return [

    // Canal de logging para eventos de auditoría RGPD.
    // Por defecto 'stack' (mismo que el log general). En producción,
    // define un canal dedicado en config/logging.php llamado 'audit'
    // (archivo separado con rotación larga) y cámbialo aquí.
    'log_channel' => env('AUDIT_LOG_CHANNEL', 'stack'),

    // Email interno al que notificar eventos críticos de privacidad
    // (borrado de cuenta, etc.). Usado por el DPO.
    'dpo_email' => env('DPO_EMAIL', 'dpo@moda.local'),
];
