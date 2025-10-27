module.exports = {
  apps: [
    {
      name: 'deschide_frontend',
      script: 'node_modules/.bin/next',
      args: 'dev',
      cwd: '/var/www/deschide_news_app/deschide_frontend',
      env: {
        NODE_ENV: 'development',
        PORT: 3005,
        NEXT_PUBLIC_API_URL: 'http://127.0.0.1:8081',
        NEXT_PUBLIC_MERCURE_URL: 'http://localhost:3000/.well-known/mercure',
        NEXT_PUBLIC_APP_NAME: 'Deschide News',
        NEXT_PUBLIC_DEFAULT_LOCALE: 'ro',
        NEXT_PUBLIC_AVAILABLE_LOCALES: 'ro,en,ru'
      },
      instances: 1,
      autorestart: true,
      watch: false,
      max_memory_restart: '500M',
      error_file: '/var/www/deschide_news_app/deschide_frontend/logs/error.log',
      out_file: '/var/www/deschide_news_app/deschide_frontend/logs/out.log',
      log_date_format: 'YYYY-MM-DD HH:mm:ss Z',
      time: true
    }
  ]
};
