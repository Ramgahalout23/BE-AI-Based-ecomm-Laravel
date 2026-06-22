# Deployment Guide — Queue Workers

> **Applies to:** Threvolt E-commerce Laravel Backend
> **Queue Driver:** `database` (shared hosting) / `redis` (VPS)
> **Last Updated:** June 2026

---

## Overview

This project uses **Laravel Queues** for async processing of emails, SMS, and notifications. A **queue worker** is a background process that picks up jobs from the `jobs` table and executes them.

Three queue jobs are defined:

| Job | Purpose |
|-----|---------|
| `SendEmailJob` | Sends transactional emails |
| `SendSmsJob` | Sends SMS via Twilio |
| `SendNotificationJob` | Creates in-app notifications |

Without a running queue worker, dispatched jobs will **sit in the `jobs` table forever** and never be executed.

---

## Environment Check

Before setting up a worker, confirm your `.env` file has the correct queue driver:

```
QUEUE_CONNECTION=database    # shared hosting (recommended)
— or —
QUEUE_CONNECTION=redis       # VPS (requires Redis server)

QUEUE_FAILED_DRIVER=database-uuids
```

### Required Tables

Both the `jobs` and `failed_jobs` tables must exist:

```bash
php artisan queue:table        # creates jobs table migration
php artisan queue:failed-table # creates failed_jobs migration (if not exists)
php artisan migrate
```

---

## Option 1: Shared Hosting — Cron Job

> **Use when:** You have cPanel / shared hosting without SSH access or the ability to run persistent processes.

Since shared hosting doesn't allow long-running daemons, we use a **cron job** that runs every minute, processes all pending jobs, and then shuts down.

### Step 1: Find your PHP binary path

Most shared hosting providers put PHP at one of these paths:

```bash
/usr/local/bin/php
/usr/bin/php
/usr/local/php81/bin/php   # version-specific
/opt/cpanel/ea-php81/root/usr/bin/php  # cPanel EasyApache
```

**Ask your hosting provider** for the exact path, or check via cPanel's "PHP Selector" section.

### Step 2: Find your project absolute path

The full path to your Laravel project on the server. For example:

```
/home/yourusername/public_html     # typical cPanel
/home/yourusername/domains/domain.com/public_html
```

### Step 3: Add the cron job

In **cPanel → Cron Jobs**, add a new cron job:

```
* * * * * cd /home/yourusername/public_html && /usr/local/bin/php artisan queue:work database --stop-when-empty --sleep=3 --tries=3 >> /dev/null 2>&1
```

#### What each flag does:

| Flag | Purpose |
|------|---------|
| `database` | Queue driver (must match `.env`) |
| `--stop-when-empty` | **Critical** — worker exits after all jobs are processed, preventing multiple instances from stacking up |
| `--sleep=3` | Wait 3 seconds between polling for new jobs (reduces DB load) |
| `--tries=3` | Retry failed jobs up to 3 times before moving to `failed_jobs` |
| `>> /dev/null 2>&1` | Discard output (prevents cron from emailing you every minute) |

> **⚠️ Important:** If you omit `--stop-when-empty`, a new cron process will start every minute, and **multiple workers will pile up** — exhausting server memory and hitting database connection limits.

### Step 4: Monitor failed jobs (Admin Panel)

If you deployed the queue monitoring routes, access them via:

```
GET  /api/v1/admin/queue/failed-jobs       → list failed jobs (paginated)
POST /api/v1/admin/queue/retry/{uuid}      → retry a specific failed job
POST /api/v1/admin/queue/retry-all         → retry ALL failed jobs
DELETE /api/v1/admin/queue/flush-failed    → clear all failed jobs
```

Alternatively, SSH in and run:

```bash
php artisan queue:failed      # list
php artisan queue:retry all   # retry all
php artisan queue:flush       # clear all
```

### Step 5: Verify the cron job works

Dispatch a test job and wait a minute:

```bash
php artisan tinker --execute="dispatch(new App\Jobs\SendEmailJob('test@example.com', 'Test', '<p>Hello</p>'))"
```

After ≤60 seconds, check:

```bash
php artisan queue:failed   # should be empty if successful
```

---

## Option 2: VPS — Supervisor (Recommended for Production)

> **Use when:** You have a VPS / dedicated server with root/sudo access.

**Supervisor** is a process manager that keeps your queue worker running 24/7 and restarts it automatically if it crashes.

### Step 1: Install Supervisor

```bash
sudo apt update
sudo apt install supervisor -y
```

### Step 2: Create worker configuration

Create `/etc/supervisor/conf.d/laravel-worker.conf`:

```ini
[program:laravel-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/html/artisan queue:work database --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=4
redirect_stderr=true
stdout_logfile=/var/www/html/storage/logs/worker.log
stopwaitsecs=3600
```

**Key configuration values:**

| Setting | Value | Why |
|---------|-------|-----|
| `command` | `queue:work database --sleep=3 --tries=3 --max-time=3600` | Process database queue; restart worker every hour to prevent memory leaks |
| `numprocs` | `4` | Run 4 parallel workers for higher throughput |
| `user` | `www-data` | Match your web server user for correct file permissions |
| `autostart` | `true` | Start worker when server boots |
| `autorestart` | `true` | Restart worker if it crashes |
| `stopwaitsecs` | `3600` | Don't kill a running job; wait up to 1 hour |
| `--max-time=3600` | **Critical** | Restart the PHP process every hour to prevent memory leaks |

> **Adjust paths:** Replace `/var/www/html` with your actual project path.

### Step 3: Start Supervisor

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start laravel-worker:*
```

### Step 4: Check worker status

```bash
sudo supervisorctl status

# Expected output:
# laravel-worker:laravel-worker_00   RUNNING   pid 12345, uptime 2:15:30
# laravel-worker:laravel-worker_01   RUNNING   pid 12346, uptime 2:15:30
# laravel-worker:laravel-worker_02   RUNNING   pid 12347, uptime 2:15:30
# laravel-worker:laravel-worker_03   RUNNING   pid 12348, uptime 2:15:30
```

### Step 5: Useful Supervisor commands

```bash
sudo supervisorctl status                  # check all workers
sudo supervisorctl restart laravel-worker:* # restart all workers
sudo supervisorctl stop laravel-worker:*    # stop all workers
sudo supervisorctl tail laravel-worker:00   # view worker logs
```

### Step 6: Enable Redis (Optional)

If you switch to Redis for better performance:

1. Install Redis: `sudo apt install redis-server`
2. Set `QUEUE_CONNECTION=redis` in `.env`
3. Update Supervisor command:

```ini
command=php /var/www/html/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
```

4. Consider **Laravel Horizon** for a beautiful dashboard:

```bash
composer require laravel/horizon
php artisan horizon:install
```

Then replace the worker command with:

```ini
command=php /var/www/html/artisan horizon
```

---

## Option 3: Alternative Queue Drivers

If neither shared hosting cron nor VPS supervisor works for your setup:

| Driver | Setup | Best For |
|--------|-------|----------|
| **database** | ✅ Zero setup | Shared hosting |
| **redis** | ❌ Requires Redis server | VPS, high throughput |
| **sqs** | ❌ Requires AWS account | Serverless, auto-scaling |

---

## Important Config Values

### `config/queue.php` — Key settings

```php
'database' => [
    'driver' => 'database',
    'table' => 'jobs',
    'queue' => 'default',
    'retry_after' => 90,    // ⚠️ Must be greater than any job's max execution time
    'after_commit' => false, // Set true for better data consistency
],
```

### Timeout Rules

```
retry_after (90s) > timeout (60s) > job execution time
```

- `retry_after` in `config/queue.php` — how long before Laravel releases a job back to the queue
- `--timeout` passed to `queue:work` — PHP max execution for the worker
- **`retry_after` must always be > `--timeout`**, or a job will be retried before it finishes

---

## Troubleshooting

### "No jobs processed" / Jobs stuck in queue

1. **Verify worker is running:**
   ```bash
   # Shared hosting — check cron job is enabled in cPanel
   # VPS — run:
   sudo supervisorctl status
   ```

2. **Check the `jobs` table:**
   ```sql
   SELECT COUNT(*) FROM jobs;
   SELECT * FROM jobs ORDER BY id DESC LIMIT 5;
   ```

3. **Manually process one job:**
   ```bash
   php artisan queue:work database --once --verbose
   ```

4. **Check logs:**
   ```bash
   tail -f storage/logs/laravel.log | grep "SendEmailJob\|SendSmsJob\|SendNotificationJob"
   ```

### "Class not found" errors

Run on the server:

```bash
composer dump-autoload
php artisan optimize
```

### "Too many open files" / Memory spikes

- Reduce `numprocs` in Supervisor config
- Add `--max-time=1800` to restart workers more frequently
- Switch from `database` to `redis` driver for better performance

---

## Quick Reference

### Shared Hosting (cron)

| Task | Command |
|------|---------|
| Edit cron | cPanel → Cron Jobs |
| Cron command | `* * * * * cd /home/user/public_html && /usr/local/bin/php artisan queue:work database --stop-when-empty --sleep=3 --tries=3 >> /dev/null 2>&1` |
| Check failed jobs | `php artisan queue:failed` |
| Retry all failed | `php artisan queue:retry all` |

### VPS (Supervisor)

| Task | Command |
|------|---------|
| Install | `sudo apt install supervisor` |
| Start workers | `sudo supervisorctl start laravel-worker:*` |
| Stop workers | `sudo supervisorctl stop laravel-worker:*` |
| Restart workers | `sudo supervisorctl restart laravel-worker:*` |
| Check status | `sudo supervisorctl status` |
| View logs | `sudo supervisorctl tail laravel-worker:00` |
| Config file | `/etc/supervisor/conf.d/laravel-worker.conf` |
