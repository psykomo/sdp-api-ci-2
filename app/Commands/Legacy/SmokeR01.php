<?php

namespace App\Commands\Legacy;

use App\Modules\Referensi\Services\ReferensiService;
use App\Modules\Wbp\Services\WbpQueryService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Smoke R0 + R1 (+ optional R2 create/delete) against legacy MariaDB (no HTTP).
 *
 *   php spark legacy:smoke-r01
 *   php spark legacy:smoke-r01 --write   # also create + soft-delete a temp identitas
 */
class SmokeR01 extends BaseCommand
{
    protected $group       = 'Legacy';
    protected $name        = 'legacy:smoke-r01';
    protected $description = 'Smoke-test R0/R1 reads (and optional R2 write) on db_sdp';
    protected $options     = [
        '--write'      => 'Also run R2 create + soft-delete with a synthetic NOMOR_INDUK',
        '--registrasi' => 'Also run R3 create + R4 update registrasi (identitas + perkara spine)',
    ];

    public function run(array $params)
    {
        $ref = new ReferensiService();
        $jenis = $ref->listJenisRegistrasi(true, null);
        CLI::write('R0 jenis_registrasi active: ' . count($jenis), 'green');
        if ($jenis !== []) {
            CLI::write('  sample: ' . ($jenis[0]['ID_REG'] ?? '?') . ' — ' . ($jenis[0]['DESKRIPSI'] ?? ''), 'white');
        }

        $agama = $ref->listLookupsByGroup('Agama');
        CLI::write('R0 lookups Agama: ' . count($agama), 'green');

        $upt = $ref->listUpt('093', 5);
        CLI::write('R0 upt search 093: ' . count($upt), 'green');

        $wbp = new WbpQueryService();
        $list = $wbp->list(10, null, 1);
        CLI::write('R1 identitas list total=' . $list['meta']['total'] . ' page_items=' . count($list['items']), 'green');
        if ($list['items'] !== []) {
            $first = $list['items'][0];
            CLI::write('  sample: ' . ($first['nomor_induk'] ?? '') . ' — ' . ($first['nama_lengkap'] ?? ''), 'white');
            $show = $wbp->findOrFail((string) $first['nomor_induk']);
            CLI::write('R1 show perkara count: ' . count($show['perkara'] ?? []), 'green');
        }

        $regList = $wbp->listRegistrasi(5, null, 1);
        CLI::write(
            'R6 registrasi list total=' . $regList['meta']['total'] . ' page_items=' . count($regList['items']),
            'green',
        );

        if (CLI::getOption('write') !== null || in_array('--write', $params, true)) {
            $this->smokeR2Write();
        }
        if (CLI::getOption('registrasi') !== null || in_array('--registrasi', $params, true)) {
            $this->smokeR3AndR4Registrasi();
        }

        CLI::write('legacy:smoke-r01 OK', 'green');
    }

    private function smokeR2Write(): void
    {
        // Bind a fake unit org context without HTTP
        $org = model(\App\Models\OrganizationModel::class, false)
            ->where('code', '093')
            ->first();
        if ($org === null) {
            CLI::error('R2 smoke skipped: organization code 093 not seeded');
            return;
        }
        $orgId = is_array($org) ? (int) $org['id'] : (int) $org->id;
        $ctx   = service('orgContext');
        $ctx->setUserId(1);
        $ctx->setActiveOrgId($orgId);
        $ctx->setScopedOrgIds([$orgId]);
        $ctx->setPermissions(['wbp.read', 'wbp.write', 'wbp.delete']);

        $svc  = new \App\Modules\Wbp\Services\WbpService(orgContext: $ctx);
        $name = 'Smoke R2 ' . date('His');
        $created = $svc->create([
            'nama_lengkap'      => $name,
            'id_jenis_kelamin'  => 'L',
            'alamat'            => 'Alamat smoke test',
        ]);
        $ni = (string) ($created['nomor_induk'] ?? '');
        CLI::write("R2 create: {$ni} — {$name}", 'green');

        $updated = $svc->update($ni, ['nama_lengkap' => $name . ' UPD']);
        CLI::write('R2 update: ' . ($updated['nama_lengkap'] ?? ''), 'green');

        $svc->delete($ni);
        CLI::write("R2 soft-delete: {$ni}", 'green');
    }

    private function smokeR3AndR4Registrasi(): void
    {
        $org = model(\App\Models\OrganizationModel::class, false)
            ->where('code', '093')
            ->first();
        if ($org === null) {
            CLI::error('R3/R4 smoke skipped: organization code 093 not seeded');

            return;
        }
        $orgId = is_array($org) ? (int) $org['id'] : (int) $org->id;
        $ctx   = service('orgContext');
        $ctx->setUserId(1);
        $ctx->setActiveOrgId($orgId);
        $ctx->setScopedOrgIds([$orgId]);
        $ctx->setPermissions(['*']);

        $svc = new \App\Modules\Wbp\Services\WbpService(orgContext: $ctx);
        $created = $svc->create([
            'nama_lengkap'     => 'R3 Smoke ' . date('His'),
            'id_jenis_kelamin' => 'L',
            'alamat'           => 'Alamat R3 smoke',
        ]);
        $ni = (string) ($created['nomor_induk'] ?? '');
        CLI::write("R3 identitas: {$ni}", 'green');

        $reg = $svc->createRegistrasi([
            'nomor_induk'  => $ni,
            'id_reg'       => 'BI',
            'nmr_reg_gol'  => 'BI.SMOKE/' . date('Y'),
            'tgl_msk_lapas'=> date('Y-m-d'),
            'tgl_ekspirasi'=> date('Y-m-d', strtotime('+1 year')),
            'kejahatan'    => [[
                'pasal_utama'        => '114 (1)',
                'uu_kejahatan'       => 'UU Smoke',
                'is_kejahatan_utama' => 1,
            ]],
            'hukuman' => [
                'id_jenis_hukuman' => 'PID',
                'thn_kurung'       => 1,
                'bln_kurung'       => 6,
                'hr_kurung'        => 0,
                'tgl_putusan'      => date('Y-m-d'),
                'nmr_putusan'      => 'SMOKE/R3',
            ],
        ]);
        $idPerkara = (string) ($reg['id_perkara'] ?? '');
        CLI::write(
            'R3 registrasi: perkara=' . $idPerkara
            . ' history=' . ($reg['id_history_reg'] ?? '')
            . ' kej=' . count($reg['kejahatan'] ?? [])
            . ' huk=' . (($reg['hukuman']['id_hkman'] ?? '') !== '' ? '1' : '0'),
            'green',
        );

        // R4 — edit perkara fields, replace kejahatan, upsert hukuman
        $updated = $svc->updateRegistrasi($idPerkara, [
            'nmr_reg_gol'   => 'BI.SMOKE-R4/' . date('Y'),
            'tgl_ekspirasi' => date('Y-m-d', strtotime('+2 years')),
            'keterangan'    => 'API R4 smoke',
            'kejahatan'     => [[
                'pasal_utama'        => '114 (2) R4',
                'uu_kejahatan'       => 'UU Smoke R4',
                'is_kejahatan_utama' => 1,
            ]],
            'hukuman' => [
                'id_jenis_hukuman' => 'PID',
                'thn_kurung'       => 2,
                'bln_kurung'       => 0,
                'hr_kurung'        => 0,
                'nmr_putusan'      => 'SMOKE/R4',
            ],
        ]);
        CLI::write(
            'R4 update: nmr=' . ($updated['nmr_reg_gol'] ?? '')
            . ' kej=' . count($updated['kejahatan'] ?? [])
            . ' hist=' . ($updated['history_count'] ?? '?')
            . ' thn_huk=' . ($updated['tahun_hukuman'] ?? ($updated['hukuman']['thn_kurung'] ?? '')),
            'green',
        );

        $show = $svc->findRegistrasiOrFail($idPerkara);
        if (($show['nmr_reg_gol'] ?? '') !== 'BI.SMOKE-R4/' . date('Y')) {
            CLI::error('R4 smoke failed: nmr_reg_gol not updated');

            return;
        }
        if ((int) ($show['history_count'] ?? 0) < 2) {
            CLI::error('R4 smoke failed: expected history_count >= 2 after create+update');

            return;
        }
        CLI::write('R4 show OK history_count=' . $show['history_count'], 'green');
    }
}
