<?php

namespace App\Modules\Wbp\Support;

/**
 * Maps API (snake_case) payloads ↔ legacy identitas column names.
 */
final class IdentitasFieldMap
{
    /**
     * API field => DB column (writable subset for R2).
     *
     * @var array<string, string>
     */
    public const API_TO_DB = [
        'nomor_induk'              => 'NOMOR_INDUK',
        'nama_lengkap'             => 'NAMA_LENGKAP',
        'nama_alias1'              => 'NAMA_ALIAS1',
        'nama_alias2'              => 'NAMA_ALIAS2',
        'nama_alias3'              => 'NAMA_ALIAS3',
        'tanggal_lahir'            => 'TANGGAL_LAHIR',
        'id_jenis_kelamin'         => 'ID_JENIS_KELAMIN',
        'id_tempat_lahir'          => 'ID_TEMPAT_LAHIR',
        'id_tempat_lahir_lain'     => 'ID_TEMPAT_LAHIR_LAIN',
        'alamat'                   => 'ALAMAT',
        'nik'                      => 'NIK',
        'id_jenis_agama'           => 'ID_JENIS_AGAMA',
        'id_jenis_pekerjaan'       => 'ID_JENIS_PEKERJAAN',
        'id_jenis_warganegara'     => 'ID_JENIS_WARGANEGARA',
        'id_propinsi'              => 'ID_PROPINSI',
        'telepon'                  => 'TELEPON',
        'kodepos'                  => 'KODEPOS',
        'nm_ayah'                  => 'NM_AYAH',
        'nm_ibu'                   => 'NM_IBU',
        'tinggi'                   => 'TINGGI',
        'berat'                    => 'BERAT',
        'id_jenis_pendidikan'      => 'ID_JENIS_PENDIDIKAN',
        'id_jenis_status_perkawinan' => 'ID_JENIS_STATUS_PERKAWINAN',
        'id_tingkat_penghasilan'   => 'ID_TINGKAT_PENGHASILAN',
        'residivis'                => 'RESIDIVIS',
    ];

    /**
     * @param array<string, mixed> $api
     * @return array<string, mixed> DB column => value
     */
    public static function toDb(array $api, bool $forUpdate = false): array
    {
        $out = [];
        foreach (self::API_TO_DB as $apiKey => $dbKey) {
            if (! array_key_exists($apiKey, $api)) {
                // Also accept raw legacy keys
                if (array_key_exists($dbKey, $api)) {
                    $out[$dbKey] = $api[$dbKey];
                }
                continue;
            }
            if ($forUpdate && $apiKey === 'nomor_induk') {
                continue; // PK immutable
            }
            $out[$dbKey] = $api[$apiKey];
        }

        return $out;
    }
}
