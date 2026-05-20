<?php

use Illuminate\Support\Facades\Schedule;

// 07:00 setiap hari — morning briefing ke semua toko aktif
Schedule::command('umkm:morning-briefing')->dailyAt('07:00');

// 08:00 setiap hari — bundle notifikasi penting
Schedule::command('umkm:send-notifications')->dailyAt('08:00');

// Setiap Senin 08:00 — bundle notifikasi info mingguan
Schedule::command('umkm:send-notifications --weekly')->weeklyOn(1, '08:00');

// Setiap jam — reminder pesanan pending > 24 jam
Schedule::command('umkm:reminder-pesanan')->hourly();

// 09:00 setiap hari — cek expiry langganan
Schedule::command('umkm:cek-expiry')->dailyAt('09:00');

// 03:00 setiap hari — cleanup stale sessions
Schedule::command('umkm:cleanup-sessions')->dailyAt('03:00');

// 02:00 setiap hari — hitung ulang RFM semua pelanggan
Schedule::command('umkm:recalculate-rfm')->dailyAt('02:00');
