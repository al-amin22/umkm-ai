// PM2 ecosystem config
// Gunakan: pm2 start ecosystem.config.cjs
// File ini .cjs karena package.json menggunakan "type": "module"

module.exports = {
  apps: [
    {
      name:         'wa-service',
      script:       'index.js',
      cwd:          __dirname,
      interpreter:  'node',
      instances:    1,
      exec_mode:    'fork',

      // Memory & restart
      max_memory_restart: '500M',
      cron_restart:       '0 3 * * *', // restart jam 03:00 setiap hari

      // Watcher (nonaktif di production)
      watch:        false,
      ignore_watch: ['node_modules', 'sessions', 'logs'],

      // Environment
      env: {
        NODE_ENV: 'production',
      },
      env_development: {
        NODE_ENV: 'development',
      },

      // Logging
      out_file:        './logs/pm2-out.log',
      error_file:      './logs/pm2-error.log',
      merge_logs:      true,
      log_date_format: 'YYYY-MM-DD HH:mm:ss',

      // Restart policy
      autorestart:       true,
      restart_delay:     5000,  // tunggu 5 detik sebelum restart
      max_restarts:      10,
      min_uptime:        '10s',

      // Graceful shutdown
      kill_timeout:      5000,
      wait_ready:        false,
      listen_timeout:    10000,
    },
  ],
};
