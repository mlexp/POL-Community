<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FfxiMasterSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('games')->upsert([
            ['code' => 'ffxi', 'name' => 'FINAL FANTASY XI', 'is_enabled' => true],
        ], ['code'], ['name', 'is_enabled']);

        $this->seedMaster('ffxi_worlds', [
            ['bahamut', 'Bahamut'], ['shiva', 'Shiva'], ['phoenix', 'Phoenix'],
            ['carbuncle', 'Carbuncle'], ['fenrir', 'Fenrir'], ['sylph', 'Sylph'],
            ['valefor', 'Valefor'], ['leviathan', 'Leviathan'], ['odin', 'Odin'],
            ['quetzalcoatl', 'Quetzalcoatl'], ['siren', 'Siren'], ['ragnarok', 'Ragnarok'],
            ['cerberus', 'Cerberus'], ['bismarck', 'Bismarck'], ['lakshmi', 'Lakshmi'], ['asura', 'Asura'],
        ]);

        $this->seedMaster('ffxi_nations', [
            ['sandoria', 'サンドリア王国', "Kingdom of San d'Oria"],
            ['bastok', 'バストゥーク共和国', 'Republic of Bastok'],
            ['windurst', 'ウィンダス連邦', 'Federation of Windurst'],
        ]);
        foreach (['sandoria' => 'assets/ffxi/flags/ffxi_flg_01.jpg', 'bastok' => 'assets/ffxi/flags/ffxi_flg_02.jpg', 'windurst' => 'assets/ffxi/flags/ffxi_flg_03.jpg'] as $code => $path) {
            DB::table('ffxi_nations')->where('code', $code)->update(['flag_path' => $path]);
        }

        $this->seedMaster('ffxi_races', [
            ['hume', 'ヒューム', 'Hume'], ['elvaan', 'エルヴァーン', 'Elvaan'],
            ['tarutaru', 'タルタル', 'Tarutaru'], ['mithra', 'ミスラ', 'Mithra'], ['galka', 'ガルカ', 'Galka'],
        ]);

        $this->seedMaster('ffxi_jobs', [
            ['war', '戦士', 'Warrior'], ['mnk', 'モンク', 'Monk'], ['whm', '白魔道士', 'White Mage'],
            ['blm', '黒魔道士', 'Black Mage'], ['rdm', '赤魔道士', 'Red Mage'], ['thf', 'シーフ', 'Thief'],
            ['pld', 'ナイト', 'Paladin'], ['drk', '暗黒騎士', 'Dark Knight'], ['rng', '狩人', 'Ranger'],
            ['brd', '吟遊詩人', 'Bard'], ['bst', '獣使い', 'Beastmaster'], ['drg', '竜騎士', 'Dragoon'],
            ['smn', '召喚士', 'Summoner'], ['nin', '忍者', 'Ninja'], ['sam', '侍', 'Samurai'],
            ['blu', '青魔道士', 'Blue Mage'], ['cor', 'コルセア', 'Corsair'], ['pup', 'からくり士', 'Puppetmaster'],
            ['dnc', '踊り子', 'Dancer'], ['sch', '学者', 'Scholar'], ['geo', '風水士', 'Geomancer'],
            ['run', '魔導剣士', 'Rune Fencer'],
        ]);

        $this->seedMaster('ffxi_crafts', [
            ['smithing', '鍛冶', 'Smithing'], ['clothcraft', '裁縫', 'Clothcraft'],
            ['alchemy', '錬金術', 'Alchemy'], ['woodworking', '木工', 'Woodworking'],
            ['goldsmithing', '彫金', 'Goldsmithing'], ['leathercraft', '革細工', 'Leathercraft'],
            ['bonecraft', '骨細工', 'Bonecraft'], ['fishing', '釣り', 'Fishing'],
            ['cooking', '調理', 'Cooking'], ['synergy', '錬成', 'Synergy'],
        ]);

        $raceIds = DB::table('ffxi_races')->pluck('id', 'code');
        $faces = [];
        $genders = ['hume' => ['male', 'female'], 'elvaan' => ['male', 'female'], 'tarutaru' => ['male', 'female'], 'mithra' => ['female'], 'galka' => ['male']];
        $prefixes = ['hume:male' => 'h', 'hume:female' => 'hh', 'elvaan:male' => 'e', 'elvaan:female' => 'ee', 'tarutaru:male' => 't', 'tarutaru:female' => 'tt', 'mithra:female' => 'm', 'galka:male' => 'g'];
        foreach ($raceIds as $raceCode => $raceId) {
            foreach ($genders[$raceCode] as $gender) {
                foreach (range(1, 8) as $number) {
                    foreach (['a', 'b'] as $variant) {
                        $code = $number.$variant;
                        $faces[] = [
                            'race_id' => $raceId,
                            'gender' => $gender,
                            'face_code' => $code,
                            'name_ja' => strtoupper($raceCode).' '.($gender === 'male' ? '男性' : '女性').' '.strtoupper($code),
                            'name_en' => strtoupper($raceCode).' '.ucfirst($gender).' '.strtoupper($code),
                            'image_path' => 'assets/ffxi/faces/'.$prefixes[$raceCode.':'.$gender].$number.'_'.$variant.'.jpg',
                            'sort_order' => (($number - 1) * 2) + ($variant === 'a' ? 1 : 2),
                            'is_active' => true,
                        ];
                    }
                }
            }
        }
        DB::table('ffxi_face_types')->upsert($faces, ['race_id', 'gender', 'face_code'], ['name_ja', 'name_en', 'image_path', 'sort_order', 'is_active']);
    }

    /** @param array<int, array{0: string, 1: string, 2?: string}> $values */
    private function seedMaster(string $table, array $values): void
    {
        $rows = array_map(fn (array $value, int $index) => [
            'code' => $value[0],
            'name_ja' => $value[1],
            'name_en' => $value[2] ?? $value[1],
            'sort_order' => $index + 1,
            'is_active' => true,
        ], $values, array_keys($values));

        DB::table($table)->upsert($rows, ['code'], ['name_ja', 'name_en', 'sort_order', 'is_active']);
    }
}
