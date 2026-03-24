module.exports = {
  apps: [
    {
      // Production configuration (standalone build)
      name: 'deschide-frontend',
      script: '.next/standalone/server.js',
      cwd: '/var/www/deschide_news_app/apps/frontend',
      instances: 'max',
      exec_mode: 'cluster',
      max_memory_restart: '512M',
      env: {
        NODE_ENV: 'development',
        PORT: 3005,
        HOSTNAME: '0.0.0.0'
      },
      env_production: {
        NODE_ENV: 'production',
        PORT: 3005,
        HOSTNAME: '0.0.0.0'
      },
      error_file: '/var/log/pm2/deschide-error.log',
      out_file: '/var/log/pm2/deschide-out.log',
      merge_logs: true,
      time: true,
      // Graceful shutdown
      kill_timeout: 5000,
      listen_timeout: 10000,
      // Restart policy
      exp_backoff_restart_delay: 100,
      max_restarts: 10,
      min_uptime: '10s',
      autorestart: true
    },
    {
      // Development configuration (next dev)
      name: 'deschide-frontend-dev',
      script: 'node_modules/.bin/next',
      args: 'dev --port 3005',
      cwd: '/var/www/deschide_news_app/apps/frontend',
      instances: 1,
      exec_mode: 'fork',
      max_memory_restart: '1G',
      env: {
        NODE_ENV: 'development',
        PORT: 3005,
        HOSTNAME: 'localhost'
      },
      error_file: '/var/log/pm2/deschide-dev-error.log',
      out_file: '/var/log/pm2/deschide-dev-out.log',
      merge_logs: true,
      time: true,
      watch: false,
      autorestart: true
    }
  ]
};
