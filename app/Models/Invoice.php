<?php

namespace App\Models;

use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $student_id
 * @property int $term_id
 * @property string $number
 * @property Carbon $issue_date
 * @property Carbon $due_date
 * @property string $total
 * @property string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Student $student
 * @property-read AcademicTerm $term
 * @property-read Collection<int, InvoiceItem> $items
 * @property-read Collection<int, Payment> $payments
 */
#[Fillable(['student_id', 'term_id', 'number', 'issue_date', 'due_date', 'total', 'status'])]
class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'due_date' => 'date',
            'total' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<AcademicTerm, $this>
     */
    public function term(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class, 'term_id');
    }

    /**
     * @return HasMany<InvoiceItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class, 'invoice_id');
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'invoice_id');
    }

    /**
     * Get the total of succeeded payments against this invoice.
     */
    public function paidTotal(): float
    {
        return (float) $this->payments()
            ->where('status', 'succeeded')
            ->sum('amount');
    }

    /**
     * Get the remaining balance on this invoice.
     */
    public function balance(): float
    {
        return round(max(0.0, (float) $this->total - $this->paidTotal()), 2);
    }

    /**
     * Scope a query to unpaid (not fully settled) invoices.
     *
     * @param  Builder<static>  $query
     */
    public function scopeOutstanding(Builder $query): void
    {
        $query->whereIn('status', ['unpaid', 'partial', 'overdue']);
    }
}
