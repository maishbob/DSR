<?php

namespace App\Models;

use App\Models\Concerns\BelongsToStation;
use Illuminate\Database\Eloquent\Model;

class PumpNozzle extends Model
{
    use BelongsToStation;
    protected $fillable = [
        'station_id',
        'product_id',
        'tank_id',
        'nozzle_name',
        'nozzle_ref',
        'sort_order',
        'main_pump',
        'nozzle_no',
        'is_active',
        'last_mech',
        'last_elec',
        'last_shs',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'last_mech'  => 'decimal:1',
        'last_elec'  => 'decimal:3',
        'last_shs'   => 'decimal:2',
    ];

    public function station()
    {
        return $this->belongsTo(Station::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function tank()
    {
        return $this->belongsTo(Tank::class);
    }

    public function meterReadings()
    {
        return $this->hasMany(MeterReading::class, 'nozzle_id');
    }

    public function latestReading()
    {
        return $this->hasOne(MeterReading::class, 'nozzle_id')->latestOfMany();
    }
}
