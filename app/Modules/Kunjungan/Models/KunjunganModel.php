<?php

namespace App\Modules\Kunjungan\Models;

use App\Modules\Kunjungan\Entities\Kunjungan;
use CodeIgniter\Model;

class KunjunganModel extends Model
{
    protected $table            = 'visits';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = Kunjungan::class;
    protected $useSoftDeletes   = true;
    protected $allowedFields    = [
        'organization_id',
        'inmate_id',
        'visitor_name',
        'visitor_id_number',
        'visited_at',
        'status',
        'notes',
    ];
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    protected $validationRules = [
        'organization_id'   => 'required|is_natural_no_zero',
        'inmate_id'         => 'permit_empty|is_natural_no_zero',
        'visitor_name'      => 'required|min_length[2]|max_length[255]',
        'visitor_id_number' => 'permit_empty|max_length[50]',
        'visited_at'        => 'required|valid_date[Y-m-d H:i:s]',
        'status'            => 'permit_empty|in_list[scheduled,completed,cancelled]',
        'notes'             => 'permit_empty|max_length[2000]',
    ];
}
