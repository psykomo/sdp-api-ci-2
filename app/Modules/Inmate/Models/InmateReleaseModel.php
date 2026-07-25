<?php

namespace App\Modules\Inmate\Models;

use App\Modules\Inmate\Entities\InmateRelease;
use CodeIgniter\Model;

class InmateReleaseModel extends Model
{
    protected $table            = 'inmate_releases';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = InmateRelease::class;
    protected $allowedFields    = [
        'inmate_id',
        'organization_id',
        'release_type',
        'release_date',
        'decree_number',
        'released_by',
        'notes',
    ];
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'inmate_id'       => 'required|is_natural_no_zero',
        'organization_id' => 'required|is_natural_no_zero',
        'release_type'    => 'required|in_list[bebas_murni,cmb,pb,cb,asimilasi,amnesti]',
        'release_date'    => 'required|valid_date[Y-m-d]',
        'decree_number'   => 'permit_empty|max_length[100]',
        'released_by'     => 'permit_empty|is_natural_no_zero',
        'notes'           => 'permit_empty|max_length[2000]',
    ];
}
