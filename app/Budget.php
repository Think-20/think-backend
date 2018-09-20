<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Budget extends Model {
    
    protected $table = 'budget';
    protected $fillable = [
        'task_id', 'responsible_id', 'gross_value', 'optional_value', 'bv_value', 'equipments_value', 
        'logistics_value', 'sales_commission_value', 'tax_aliquot', 'others_value', 'markup_aliquot'
    ];

    public static function insert(array $data) {
        $task_id = isset($data['task']['id']) ? $data['task']['id'] : null;
        $responsible_id = User::logged()->employee->id;

        $budget = new Budget(array_merge($data, [
            'task_id' => $task_id,
            'responsible_id' => $responsible_id
        ]));

        $budget->save();
        
        return $budget;
    }

    public static function edit(array $data) {
        $id = isset($data['id']) ? $data['id'] : null;
        $task_id = isset($data['task']['id']) ? $data['task']['id'] : null;
        $responsible_id = User::logged()->employee->id;

        $budget = Budget::find($id);
        
        $budget->update(array_merge($data, [
            'task_id' => $task_id,
            'responsible_id' => $responsible_id
        ]));
        
        return $budget;
    }

    public static function remove($id) {
        $budget = Budget::find($id);
        $budget->delete();
    }

    public function responsible()
    {
        return $this->belongsTo('App\Employee', 'responsible_id');
    }

    public function setGrossValueAttribute($value)
    {
        $this->attributes['gross_value'] = (float)str_replace(',', '.', $value);
    }

    public function setOptionalValueAttribute($value)
    {
        $this->attributes['optional_value'] = (float)str_replace(',', '.', $value);
    }

    public function setBvValueAttribute($value)
    {
        $this->attributes['bv_value'] = (float)str_replace(',', '.', $value);
    }

    public function setEquipmentsValueAttribute($value)
    {
        $this->attributes['equipments_value'] = (float)str_replace(',', '.', $value);
    }

    public function setLogisticsValueAttribute($value)
    {
        $this->attributes['logistics_value'] = (float)str_replace(',', '.', $value);
    }

    public function setSalesCommissionValueAttribute($value)
    {
        $this->attributes['sales_commission_value'] = (float)str_replace(',', '.', $value);
    }

    public function setTaxAliquotAttribute($value)
    {
        $this->attributes['tax_aliquot'] = (float)str_replace(',', '.', $value);
    }

    public function setOthersValueAttribute($value)
    {
        $this->attributes['others_value'] = (float)str_replace(',', '.', $value);
    }

    public function setMarkupAliquotAttribute($value)
    {
        $this->attributes['markup_aliquot'] = (float)str_replace(',', '.', $value);
    }


}
