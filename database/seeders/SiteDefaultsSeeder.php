<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SiteDefaultsSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $settings = [
            'site.title' => ['POL Community', true],
            'site.description' => ['', true],
            'site.footer_text' => ['', true],
            'site.timezone' => ['Asia/Tokyo', true],
            'registration.enabled' => [true, true],
            'registration.require_admin_approval' => [true, false],
            'home.announcement_limit' => [10, true],
            'home.diary_limit' => [10, true],
            'home.feed_item_limit' => [10, true],
            'upload.image_max_bytes' => [1048576, false],
            'community.posting_enabled' => [true, true],
            'comments.enabled' => [true, true],
        ];

        foreach ($settings as $key => [$value, $isPublic]) {
            DB::table('site_settings')->updateOrInsert(
                ['key' => $key],
                ['value' => json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), 'is_public' => $isPublic, 'updated_at' => $now],
            );
        }

        $categories = [
            ['name' => '未分類', 'slug' => 'uncategorized', 'color' => '#71717A', 'sort_order' => 0],
            ['name' => '雑談', 'slug' => 'general', 'color' => '#3B82F6', 'sort_order' => 10],
            ['name' => 'ご意見募集', 'slug' => 'feedback', 'color' => '#8B5CF6', 'sort_order' => 20],
            ['name' => 'メンバー募集', 'slug' => 'recruitment', 'color' => '#10B981', 'sort_order' => 30],
            ['name' => '質問', 'slug' => 'questions', 'color' => '#F59E0B', 'sort_order' => 40],
        ];

        foreach ($categories as $category) {
            DB::table('community_categories')->updateOrInsert(
                ['slug' => $category['slug']],
                [...$category, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            );
        }

        DB::table('diary_categories')->updateOrInsert(
            ['slug' => 'uncategorized'],
            ['name' => '未分類', 'description' => null, 'sort_order' => 0, 'created_at' => $now, 'updated_at' => $now],
        );
    }
}
