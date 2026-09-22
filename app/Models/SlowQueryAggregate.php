<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int         $id
 * @property string      $query_hash
 * @property string      $sql_sample
 * @property string|null $bindings_sample
 * @property string|null $route_name
 * @property int         $count
 * @property float       $avg_ms
 * @property float       $max_ms
 * @property float       $total_ms
 * @property \Illuminate\Support\Carbon $first_seen_at
 * @property \Illuminate\Support\Carbon $last_seen_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static Builder<static>|SlowQueryAggregate newModelQuery()
 * @method static Builder<static>|SlowQueryAggregate newQuery()
 * @method static Builder<static>|SlowQueryAggregate query()
 * @method static Builder<static>|SlowQueryAggregate orderByAvg()
 * @method static Builder<static>|SlowQueryAggregate orderByTotal()
 * @method static Builder<static>|SlowQueryAggregate orderByCount()
 * @method static Builder<static>|SlowQueryAggregate orderByRecency()
 * @mixin \Eloquent
 */
class SlowQueryAggregate extends Model
{
    /**
     * No $fillable — the harvest command is the ONLY writer and its
     * payload is fully controlled. $guarded = [] keeps the create()
     * call in the command concise.
     */
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'count'         => 'integer',
            'avg_ms'        => 'float',
            'max_ms'        => 'float',
            'total_ms'      => 'float',
            'first_seen_at' => 'datetime',
            'last_seen_at'  => 'datetime',
        ];
    }

    // ── Sort scopes (one per dashboard pill) ─────────────

    public function scopeOrderByAvg(Builder $q): Builder
    {
        return $q->orderByDesc('avg_ms');
    }

    public function scopeOrderByTotal(Builder $q): Builder
    {
        return $q->orderByDesc('total_ms');
    }

    public function scopeOrderByCount(Builder $q): Builder
    {
        return $q->orderByDesc('count');
    }

    public function scopeOrderByRecency(Builder $q): Builder
    {
        return $q->orderByDesc('last_seen_at');
    }
}