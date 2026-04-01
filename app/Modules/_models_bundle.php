<?php
// ═══════════════════════════════════════════════════════════════
// Student Model
// ═══════════════════════════════════════════════════════════════
namespace App\Modules\Students\Models;

use App\Modules\Courses\Models\Enrollment;
use App\Modules\Finance\Models\Invoice;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Student extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'tenant_id','lead_id','first_name','last_name','email','phone',
        'date_of_birth','nationality','nit','status','avatar_url',
        'notes','enrolled_at','graduated_at','dropped_at',
    ];

    protected $casts = [
        'enrolled_at'   => 'datetime',
        'graduated_at'  => 'datetime',
        'dropped_at'    => 'datetime',
        'date_of_birth' => 'date',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope('tenant', function ($b) {
            if (app()->bound('tenant_id')) {
                $b->where('students.tenant_id', app('tenant_id'));
            }
        });
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function enrollments()
    {
        return $this->hasMany(Enrollment::class);
    }

    public function activeEnrollments()
    {
        return $this->hasMany(Enrollment::class)->where('status', 'active');
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function pendingInvoices()
    {
        return $this->hasMany(Invoice::class)->whereIn('status', ['pending', 'partial', 'overdue']);
    }

    public function lead()
    {
        return $this->belongsTo(\App\Modules\CRM\Models\Lead::class);
    }
}


// ═══════════════════════════════════════════════════════════════
// Course Model
// ═══════════════════════════════════════════════════════════════
namespace App\Modules\Courses\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Course extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'tenant_id','coach_id','name','type','level','description',
        'capacity','price','currency','status','schedule_description',
        'starts_at','ends_at',
    ];

    protected $casts = [
        'price'      => 'float',
        'capacity'   => 'integer',
        'starts_at'  => 'datetime',
        'ends_at'    => 'datetime',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope('tenant', function ($b) {
            if (app()->bound('tenant_id')) {
                $b->where('courses.tenant_id', app('tenant_id'));
            }
        });
    }

    public function enrollments()
    {
        return $this->hasMany(Enrollment::class);
    }

    public function activeStudents()
    {
        return $this->hasMany(Enrollment::class)->where('status', 'active');
    }

    public function coach()
    {
        return $this->belongsTo(\App\Models\User::class, 'coach_id');
    }

    public function getRemainingCapacityAttribute(): int
    {
        return max(0, $this->capacity - $this->activeStudents()->count());
    }
}


// ═══════════════════════════════════════════════════════════════
// Enrollment Model
// ═══════════════════════════════════════════════════════════════
namespace App\Modules\Courses\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Enrollment extends Model
{
    use HasUuids;

    protected $fillable = [
        'student_id','course_id','status','enrolled_at',
        'completed_at','dropped_at','notes',
    ];

    protected $casts = [
        'enrolled_at'   => 'datetime',
        'completed_at'  => 'datetime',
        'dropped_at'    => 'datetime',
    ];

    public function student()
    {
        return $this->belongsTo(\App\Modules\Students\Models\Student::class);
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }
}


// ═══════════════════════════════════════════════════════════════
// Invoice Model
// ═══════════════════════════════════════════════════════════════
namespace App\Modules\Finance\Models;

use App\Modules\Students\Models\Student;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'tenant_id','student_id','number','status','subtotal',
        'tax','discount','total','currency','due_date','paid_at','notes',
    ];

    protected $casts = [
        'subtotal'  => 'float',
        'tax'       => 'float',
        'discount'  => 'float',
        'total'     => 'float',
        'due_date'  => 'date',
        'paid_at'   => 'datetime',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope('tenant', function ($b) {
            if (app()->bound('tenant_id')) {
                $b->where('invoices.tenant_id', app('tenant_id'));
            }
        });
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->status === 'pending' && $this->due_date->isPast();
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function items()
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function getTotalPaidAttribute(): float
    {
        return $this->payments()->where('status', 'completed')->sum('amount');
    }

    public function getBalanceDueAttribute(): float
    {
        return max(0, $this->total - $this->total_paid);
    }

    public function scopeOverdue($query)
    {
        return $query->where('status', 'pending')
                     ->where('due_date', '<', now());
    }
}


// ═══════════════════════════════════════════════════════════════
// InvoiceItem + Payment models
// ═══════════════════════════════════════════════════════════════
namespace App\Modules\Finance\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class InvoiceItem extends Model
{
    use HasUuids;

    protected $fillable = [
        'invoice_id','description','quantity','unit_price','total',
    ];

    protected $casts = [
        'quantity'   => 'integer',
        'unit_price' => 'float',
        'total'      => 'float',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }
}

class Payment extends Model
{
    use HasUuids;

    protected $fillable = [
        'invoice_id','amount','method','status',
        'reference','recorded_by','notes','paid_at',
    ];

    protected $casts = [
        'amount'  => 'float',
        'paid_at' => 'datetime',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }
}
