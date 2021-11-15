/**
 * @see https://pm2.keymetrics.io/docs/usage/application-declaration/
 * 启动: pm2 start 或 pm2 start ecosystem.config.js --only [appName]
 * 停止: pm2 stop all 或 pm2 stop [appName]
 * 重启: pm2 restart all 或 pm2 restart [appName]
 * 删除: pm2 del all 或 pm2 del [appName]
 * 状态: pm2 status
 * 日志: pm2 logs [appName]
 * 监控: pm2 monit
 */
module.exports = {
  apps: [
    /*{
      // 例子：启动 php 服务
      name: "yzctask-serve",
      interpreter: "php",
      script: 'artisan',
      args: 'serve',
      log_date_format: 'YYYY-MM-DD HH:mm:ss',
      error_file: './storage/logs/pm2/yzc-serve-error.log',
      out_file: './storage/logs/pm2/yzc-serve-out.log',
      combine_logs: true,
    },*/
    {
      // queue:work
      // 通过 php artisan queue:restart 重启
      name: "yzctask-queue-worker",
      interpreter: "php",
      script: 'artisan',
      args: 'queue:work --tries=5 --sleep=1',
      instances: 5,
      log_date_format: 'YYYY-MM-DD HH:mm:ss',
      error_file: './storage/logs/pm2/yzctask-queue-worker-error.log',
      out_file: './storage/logs/pm2/yzctask-queue-worker-out.log',
      combine_logs: true,
    },
  ]
}
