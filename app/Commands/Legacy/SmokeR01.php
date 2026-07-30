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
        '--registrasi' => 'Also run R3–R5 + M1 mutasi golongan (identitas + perkara spine)',
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

        // R5 — list / append / update / soft-delete history
        $histList = $svc->listHistory($idPerkara, 20, 1);
        CLI::write('R5 history list total=' . $histList['meta']['total'], 'green');
        if ($histList['items'] === []) {
            CLI::error('R5 smoke failed: expected history rows after R3/R4');

            return;
        }
        $firstHistId = (string) ($histList['items'][0]['id_history_reg'] ?? '');
        $histShow    = $svc->findHistoryOrFail($idPerkara, $firstHistId);
        CLI::write('R5 show history=' . ($histShow['id_history_reg'] ?? ''), 'green');

        $appended = $svc->createHistory($idPerkara, [
            'keterangan' => 'API R5 smoke append',
        ]);
        $appendId = (string) ($appended['id_history_reg'] ?? '');
        CLI::write('R5 append: ' . $appendId, 'green');

        $histUpd = $svc->updateHistory($idPerkara, $appendId, [
            'keterangan'  => 'API R5 smoke edit',
            'nmr_reg_gol' => 'BI.SMOKE-R5/' . date('Y'),
        ]);
        if (($histUpd['keterangan'] ?? '') !== 'API R5 smoke edit') {
            CLI::error('R5 smoke failed: keterangan not updated');

            return;
        }
        CLI::write('R5 update: nmr=' . ($histUpd['nmr_reg_gol'] ?? ''), 'green');

        $deleted = $svc->deleteHistory($idPerkara, $appendId);
        CLI::write('R5 soft-delete: ' . ($deleted['id_history_reg'] ?? ''), 'green');

        $afterDelete = $svc->listHistory($idPerkara, 50, 1);
        foreach ($afterDelete['items'] as $item) {
            if (($item['id_history_reg'] ?? '') === $appendId) {
                CLI::error('R5 smoke failed: soft-deleted history still listed');

                return;
            }
        }
        CLI::write('R5 list excludes soft-deleted OK', 'green');

        // M1 — mutasi golongan (BI → BIII; LEVEL 6 → 8)
        $this->smokeM1MutasiGolongan($ctx, $idPerkara);
    }

    private function smokeM1MutasiGolongan(\App\Services\OrgContext $ctx, string $idPerkara): void
    {
        $mutasiSvc = new \App\Modules\Mutasi\Services\MutasiGolonganService(
            orgContext: $ctx,
        );

        $opts = $mutasiSvc->options($idPerkara);
        CLI::write('M1 options count=' . count($opts), 'green');
        if ($opts === []) {
            CLI::error('M1 smoke failed: no target golongan options for perkara');

            return;
        }

        $target = null;
        foreach ($opts as $o) {
            if (($o['id_reg'] ?? '') === 'BIII') {
                $target = 'BIII';
                break;
            }
        }
        $target ??= (string) ($opts[0]['id_reg'] ?? '');

        $result = $mutasiSvc->create([
            'id_perkara'    => $idPerkara,
            'id_reg_akhir'  => $target,
            'nmr_srt_mg'    => 'M1/1',
            'tgl_srt_mg'    => date('Y-m-d'),
            'tgl_efektif'   => date('Y-m-d'),
            'nmr_reg_gol'   => $target . '.SMOKE/' . date('Y'),
            'keterangan'    => 'API M1 smoke',
        ]);
        CLI::write(
            'M1 mutasi: ' . ($result['id_reg_awal'] ?? '') . '→' . ($result['id_reg_akhir'] ?? '')
            . ' id=' . ($result['id_mutasi_tahanan'] ?? '')
            . ' hist=' . ($result['id_history_reg'] ?? ''),
            'green',
        );

        $reg = $result['registrasi'] ?? [];
        if (($reg['id_reg'] ?? '') !== $target) {
            CLI::error('M1 smoke failed: perkara.id_reg not updated to ' . $target);

            return;
        }

        $list = $mutasiSvc->listForPerkara($idPerkara, 10, 1);
        if ((int) ($list['meta']['total'] ?? 0) < 1) {
            CLI::error('M1 smoke failed: mutasi list empty after create');

            return;
        }
        $show = $mutasiSvc->findOrFail((string) ($result['id_mutasi_tahanan'] ?? ''));
        CLI::write('M1 show/list OK total=' . $list['meta']['total'] . ' reg_akhir=' . ($show['id_reg_akhir'] ?? ''), 'green');
    }
}
