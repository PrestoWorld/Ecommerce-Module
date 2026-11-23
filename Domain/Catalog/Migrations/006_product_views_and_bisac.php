<?php

declare(strict_types=1);

use App\Support\Slug;
use Cycle\Database\DatabaseInterface;
use Cycle\Database\Table;
use PrestoWorld\Database\MigrationInterface;

/**
 * 006_product_views_and_bisac
 *
 * Adds a `view_count` column to bow_product_masters (incremented on every
 * book detail view) and backfills `subject_codes` with BISAC codes derived
 * from each title's subject keywords.
 *
 * The catalog has no explicit product→category link (subject_codes /
 * categories were empty on every master), so this backfill maps common
 * Vietnamese school-subject keywords to BISAC top-level codes. Titles with
 * no keyword match fall back to EDU000000 (Education).
 */
return new class implements MigrationInterface {
    public function up(DatabaseInterface $db, string $prefix): void
    {
        $name = $prefix . 'product_masters';
        if (!$db->hasTable($name)) {
            return;
        }

        $table = $db->table($name);
        if (!$table instanceof Table) {
            return;
        }

        $schema = $table->getSchema();
        if (!$schema->hasColumn('view_count')) {
            $schema->column('view_count')->bigInteger()->defaultValue(0);
        }
        $schema->save();

        $this->backfillSubjectCodes($db, $name);
    }

    public function down(DatabaseInterface $db, string $prefix): void
    {
        $name = $prefix . 'product_masters';
        if (!$db->hasTable($name)) {
            return;
        }

        $table = $db->table($name);
        if (!$table instanceof Table) {
            return;
        }

        $schema = $table->getSchema();
        if ($schema->hasColumn('view_count')) {
            $schema->declareDropped('view_count');
        }
        $schema->save();
    }

    private function backfillSubjectCodes(DatabaseInterface $db, string $name): void
    {
        // Only touch rows that still have an empty subject_codes so re-runs
        // and manual edits are preserved.
        $rows = $db->select('id', 'title')
            ->from($name)
            ->run()
            ->fetchAll();

        foreach ($rows as $row) {
            $row = (array) $row;
            $title = trim((string) ($row['title'] ?? ''));
            if ($title === '') {
                continue;
            }

            $codes = $this->classify($title);
            $encoded = json_encode($codes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($encoded === false) {
                continue;
            }

            $db->update($name, ['subject_codes' => $encoded], ['id' => (int) $row['id']])->run();
        }
    }

    /**
     * Map a Vietnamese title to a list of BISAC codes by keyword. Accents
     * are folded via Slug::make so "Toán", "Toan", "toán" all match.
     *
     * @return list<string>
     */
    private function classify(string $title): array
    {
        $h = ' ' . str_replace('-', ' ', Slug::make($title)) . ' ';

        // Order matters: first match wins per group, but we collect all
        // distinct subject groups a title touches.
        $map = [
            'MAT000000' => [' toan ', ' dai so ', ' hinh hoc ', ' giai tich ', ' xac suat ', ' thong ke '],
            'FOR000000' => [' tieng anh ', ' anh van ', ' tieng phap ', ' tieng nhat ', ' tieng han ', ' tieng trung ', ' ngoai ngu '],
            'LAN000000' => [' tieng viet ', ' ngu van ', ' viet van ', ' tap doc ', ' tap viet ', ' luyen viet ', ' chinh ta '],
            'LIT000000' => [' van hoc ', ' thuyet minh ', ' tu bach ', ' phan tich van ', ' tac pham '],
            'SCI000000' => [' vat ly ', ' hoa hoc ', ' sinh hoc ', ' khoa hoc ', ' ly thuyet ', ' thuc hanh '],
            'HIS000000' => [' lich su ', ' su hoc ', ' lich su nuoc '],
            'NAT000000' => [' dia ly ', ' dia li ', ' tu nhien ', ' môi truong ', ' moi truong '],
            'COM000000' => [' tin hoc ', ' cong nghe thong tin ', ' lap trinh ', ' may tinh ', ' mang may tinh '],
            'BUS000000' => [' kinh te ', ' ke toan ', ' tai chinh ', ' kinh doanh ', ' quan tri ', ' marketing ', ' ngan hang ', ' bao hiem '],
            'SOC000000' => [' dao duc ', ' giao duc cong dan ', ' cong dan ', ' xa hoi ', ' kinh te chinh tri '],
            'PSY000000' => [' tam ly ', ' tam ly hoc ', ' ky nang song ', ' nuoi day con ', ' lam cha me ', ' hai yen '],
            'PHI000000' => [' triet hoc ', ' triet ly ', ' tu duy ', ' tri tue '],
            'JUV000000' => [' thieu nhi ', ' thieu dong ', ' nhi dong ', ' tranh sach ', ' co tich '],
            'ART000000' => [' my thuat ', ' hoi hoa ', ' am nhac ', ' nhac ly ', ' san khau ', ' dien anh '],
            'TEC000000' => [' cong nghe ', ' ky thuat ', ' co khi ', ' dien tu ', ' tu dong hoa ', ' xay dung '],
            'HEA000000' => [' suc khoe ', ' the duc ', ' the thao ', ' bong da ', ' bong ro ', ' duong dai '],
            'TRV000000' => [' du lich ', ' dia danh ', ' kham pha '],
            'REF000000' => [' tu dien ', ' cam nang ', ' tham khao ', ' phu luc '],
            'EDU000000' => [' giao duc ', ' giao trinh ', ' tai lieu ', ' on tap ', ' kiem tra ', ' de thi ', ' bai tap ', ' sach giao khoa ', ' sgk ', ' sach bai tap ', ' sbt '],
            'SEL000000' => [' ky nang ', ' phat trien ban than ', ' dong luc ', ' doc sach '],
            'FAM000000' => [' gia dinh ', ' hon nhan ', ' tinh yeu ', ' tre em '],
        ];

        $codes = [];
        foreach ($map as $code => $keywords) {
            foreach ($keywords as $kw) {
                if (str_contains($h, $kw)) {
                    $codes[] = $code;
                    break;
                }
            }
        }

        if ($codes === []) {
            $codes[] = 'EDU000000';
        }

        return array_values(array_unique($codes));
    }
};