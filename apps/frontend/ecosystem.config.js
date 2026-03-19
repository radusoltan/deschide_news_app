module.exports = {
  apps: [{
    name: 'deschide-frontend',
    script: '.next/standalone/server.js',
    cwd: '/var/www/deschide_news_app/apps/frontend',
    instances: 'max',
    exec_mode: 'cluster',
    env: {
      NODE_ENV: 'production',
      PORT: 3005,
      HOSTNAME: '0.0.0.0'
    },
    max_memory_restart: '500M',
    error_file: '/var/log/pm2/deschide-error.log',
    out_file: '/var/log/pm2/deschide-out.log',
    merge_logs: true,
    time: true
  }]
};
