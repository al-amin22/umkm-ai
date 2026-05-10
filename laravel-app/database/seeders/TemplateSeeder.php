<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'nama'        => 'Minimalis Putih',
                'css_class'   => 'theme-minimal-white',
                'deskripsi'   => 'Tampilan bersih dan modern dengan latar putih, cocok untuk semua jenis produk',
                'preview_url' => null,
                'is_active'   => true,
            ],
            [
                'nama'        => 'Modern Gelap',
                'css_class'   => 'theme-modern-dark',
                'deskripsi'   => 'Tampilan elegan dengan latar gelap, menonjolkan foto produk dengan dramatis',
                'preview_url' => null,
                'is_active'   => true,
            ],
            [
                'nama'        => 'Hangat Coklat',
                'css_class'   => 'theme-warm-brown',
                'deskripsi'   => 'Nuansa hangat dan natural, ideal untuk produk makanan, kerajinan, dan fashion lokal',
                'preview_url' => null,
                'is_active'   => true,
            ],
            [
                'nama'        => 'Segar Hijau',
                'css_class'   => 'theme-fresh-green',
                'deskripsi'   => 'Tampilan segar dan cerah, sempurna untuk produk organik, kesehatan, dan pertanian',
                'preview_url' => null,
                'is_active'   => true,
            ],
            [
                'nama'        => 'Elegan Ungu',
                'css_class'   => 'theme-elegant-purple',
                'deskripsi'   => 'Tampilan mewah dan berkelas, cocok untuk produk premium dan fashion',
                'preview_url' => null,
                'is_active'   => true,
            ],
        ];

        foreach ($templates as $template) {
            DB::table('templates')->updateOrInsert(
                ['nama' => $template['nama']],
                array_merge($template, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }
}
