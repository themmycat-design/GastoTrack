<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class WalletNotificationCapture extends Model
{
    private const NON_TRANSACTION_PHRASES = [
        'promo', 'promotion', 'offer', 'offers', 'discount', 'voucher', 'coupon', 'otp',
        'one-time pin', 'one-time password', 'one time pin', 'one time password',
        'verification', 'security', 'login', 'password', 'passcode', 'pin',
        'reminder', 'due', 'pending', 'failed', 'declined', 'cancelled', 'canceled',
        'unsuccessful', 'expired', 'request', 'requested', 'not received',
    ];

    private const RECEIVED_PHRASES = [
        'received', 'credited', 'deposited', 'cash in', 'cash-in', 'added money',
        'top up successful', 'top-up successful',
    ];

    private const SENT_PHRASES = [
        'sent', 'paid', 'payment of', 'payment for', 'payment to', 'payment successful',
        'payment completed', 'transferred', 'transfer to', 'transfer successful',
        'transfer completed', 'debited', 'purchase', 'purchased', 'withdrawn',
        'cash out', 'cash-out', 'charged',
    ];

    protected $fillable = [
        'client_id', 'business_id', 'user_id', 'source', 'direction',
        'amount', 'currency', 'merchant', 'counterparty_name', 'reference', 'message', 'captured_at', 'used_order_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'captured_at' => 'datetime',
    ];

    public function getDisplayCounterpartyAttribute(): ?string
    {
        return $this->counterparty_name ?: $this->merchant ?: null;
    }

    public function getDisplayReferenceAttribute(): ?string
    {
        if ($this->reference) return $this->reference;
        if (!preg_match('/\b(?:reference(?:\s*(?:id|number|no\\.?|#))?|ref\\.?(?:\s*(?:id|number|no\\.?|#))?|transaction\s+(?:reference|id|number|no\\.?)|txn\s*(?:reference|id|number|no\\.?))\s*[:#.-]?\s*([A-Z0-9][A-Z0-9._\\/-]{5,119})\b/i', (string) $this->message, $matches)) {
            return null;
        }

        return $matches[1];
    }

    public function capturedBy()
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }

    /** Hide legacy/noisy wallet alerts from every Raw Transactions surface. */
    public function scopeTransactionSignals(Builder $query): Builder
    {
        $query->whereIn('source', ['gcash', 'maya'])
            ->whereIn('direction', ['received', 'sent'])
            ->whereNotNull('amount')
            ->where('amount', '>', 0)
            ->whereNotNull('message')
            ->whereRaw("TRIM(message) <> ''")
            ->where(function (Builder $directions): void {
                $directions->where(function (Builder $received): void {
                    $received->where('direction', 'received')
                        ->where(function (Builder $terms): void {
                            foreach (self::RECEIVED_PHRASES as $phrase) {
                                self::addPhraseMatch($terms, $phrase);
                            }
                        });
                })->orWhere(function (Builder $sent): void {
                    $sent->where('direction', 'sent')
                        ->where(function (Builder $terms): void {
                            foreach (self::SENT_PHRASES as $phrase) {
                                self::addPhraseMatch($terms, $phrase);
                            }
                        });
                });
            });

        foreach (self::NON_TRANSACTION_PHRASES as $phrase) {
            if ($query->getQuery()->getConnection()->getDriverName() === 'mysql') {
                $pattern = str_replace(' ', '[[:space:]]+', preg_quote($phrase, '/'));
                $query->whereRaw('LOWER(message) NOT REGEXP ?', ['(^|[^[:alnum:]])'.$pattern.'([^[:alnum:]]|$)']);
            } else {
                $query->whereRaw('LOWER(message) NOT LIKE ?', ['%'.mb_strtolower($phrase).'%']);
            }
        }

        return $query;
    }

    private static function addPhraseMatch(Builder $query, string $phrase): void
    {
        $driver = $query->getQuery()->getConnection()->getDriverName();
        if ($driver === 'mysql') {
            $pattern = str_replace(' ', '[[:space:]]+', preg_quote($phrase, '/'));
            $query->orWhereRaw('LOWER(message) REGEXP ?', ['(^|[^[:alnum:]])'.$pattern.'([^[:alnum:]]|$)']);
            return;
        }

        $query->orWhereRaw('LOWER(message) LIKE ?', ['%'.mb_strtolower($phrase).'%']);
    }

    public static function isTransactionSignal(string $source, string $direction, mixed $amount, ?string $message): bool
    {
        if (!in_array(strtolower($source), ['gcash', 'maya'], true)
            || !in_array(strtolower($direction), ['received', 'sent'], true)
            || !is_numeric($amount)
            || (float) $amount <= 0
            || trim((string) $message) === '') {
            return false;
        }

        $normalized = mb_strtolower($message);
        foreach (self::NON_TRANSACTION_PHRASES as $phrase) {
            if (self::messageContainsPhrase($normalized, $phrase)) return false;
        }

        $phrases = strtolower($direction) === 'received' ? self::RECEIVED_PHRASES : self::SENT_PHRASES;
        foreach ($phrases as $phrase) {
            if (self::messageContainsPhrase($normalized, $phrase)) return true;
        }

        return false;
    }

    private static function messageContainsPhrase(string $message, string $phrase): bool
    {
        $pattern = '/(?<![\pL\pN])'.preg_quote($phrase, '/').'(?![\pL\pN])/iu';
        return preg_match($pattern, $message) === 1;
    }
}
