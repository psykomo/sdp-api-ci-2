<?php

namespace App\Modules\Transfer\Models;

use App\Modules\Transfer\Entities\InmateTransfer;
use CodeIgniter\Model;

class InmateTransferModel extends Model
{
    protected $table            = 'inmate_transfers';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = InmateTransfer::class;
    protected $allowedFields    = [
        'inmate_id',
        'from_organization_id',
        'to_organization_id',
        'transferred_by',
        'reason',
        'notes',
        'transferred_at',
    ];
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'inmate_id'               => 'required|is_natural_no_zero',
        'from_organization_id'    => 'required|is_natural_no_zero',
        'to_organization_id'      => 'required|is_natural_no_zero',
        'transferred_by'          => 'permit_empty|is_natural_no_zero',
        'reason'                  => 'required|max_length[255]',
        'notes'                   => 'permit_empty|max_length[2000]',
        'transferred_at'          => 'required|valid_date[Y-m-d H:i:s]',
    ];
}
