<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Mensaje recibido vía el formulario de contacto del footer. Si el
 * usuario estaba autenticado se vincula a su cuenta; si la borra, se
 * elimina en cascada (constrained). Contiene PII (nombre/email/mensaje)
 * y se trata como tal.
 */
class ContactMessage extends Model
{
    use HasFactory;

    public const SUBJECT_SHIPPING = 'shipping';
    public const SUBJECT_RETURNS = 'returns';
    public const SUBJECT_PRESS = 'press';
    public const SUBJECT_PARTNERSHIP = 'partnership';
    public const SUBJECT_OTHER = 'other';

    public const SUBJECTS = [
        self::SUBJECT_SHIPPING => 'Envíos',
        self::SUBJECT_RETURNS => 'Devoluciones',
        self::SUBJECT_PRESS => 'Prensa',
        self::SUBJECT_PARTNERSHIP => 'Colaboraciones / Afiliación',
        self::SUBJECT_OTHER => 'Otro',
    ];

    public const STATUS_NEW = 'new';
    public const STATUS_READ = 'read';
    public const STATUS_REPLIED = 'replied';
    public const STATUS_ARCHIVED = 'archived';

    protected $fillable = [
        'user_id', 'name', 'email', 'subject', 'topic', 'message',
        'status', 'ip_address', 'user_agent',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
