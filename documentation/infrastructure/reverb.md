# Laravel Reverb Infrastructure

## Purpose

Laravel Reverb is used to provide **real-time WebSocket communication** for this application. It enables push-based interactions such as live notifications, broadcasting events, and instantaneous data updates without requiring long-polling or external third-party services.

Reverb runs as a standalone process, independent of the web server (Nginx/Apache), and manages its own WebSocket connections on a dedicated port.

---

## Production Configuration

| Component       | Value               |
|-----------------|---------------------|
| Operating System| Ubuntu 24.04        |
| PHP Version     | 8.4                 |
| Service Manager | systemd             |
| Managed by PM2  | No                  |
| Started via nohup | No                |
| Started from cron | No               |

Reverb runs as a **native systemd service**. This is the only supported method of managing Reverb on this server.

---

## systemd Service

### Service File Location

```
/etc/systemd/system/reverb.service
```

### Complete Service File

```ini
[Unit]
Description=Laravel Reverb WebSocket Server
After=network.target
Wants=network.target

[Service]
User=www-data
Group=www-data
WorkingDirectory=/var/www/your-app
ExecStart=/usr/bin/php8.4 artisan reverb:start --port=8081
Restart=always
RestartSec=5
KillSignal=SIGINT
TimeoutStopSec=30
StandardOutput=journal
StandardError=journal

[Install]
WantedBy=multi-user.target
```

> **Note:** Replace `/var/www/your-app` with the actual application path.

### Directive Reference

#### `[Unit]`

| Directive    | Value                          | Purpose                                                                 |
|--------------|--------------------------------|-------------------------------------------------------------------------|
| `Description`| Laravel Reverb WebSocket Server| Human-readable label shown in `systemctl status` and logs.             |
| `After`      | `network.target`               | Ensures the network stack is fully initialized before Reverb starts.   |
| `Wants`      | `network.target`               | Soft dependency — if the network target fails, Reverb still tries to start (won't block). |

#### `[Service]`

| Directive        | Value                          | Purpose                                                                 |
|------------------|--------------------------------|-------------------------------------------------------------------------|
| `User`           | `www-data`                     | Runs the process as the standard web server user, matching Nginx/Apache permissions. |
| `Group`          | `www-data`                     | Group ownership for the process.                                        |
| `WorkingDirectory`| `/var/www/your-app`           | Sets the working directory so `artisan reverb:start` resolves correctly.|
| `ExecStart`      | `/usr/bin/php8.4 artisan reverb:start --port=8081` | The exact command executed to launch Reverb. Uses **PHP 8.4 explicitly** (see Important Notes below). |
| `Restart`        | `always`                       | Automatically restarts the process if it exits for any reason (crash, OOM, manual stop). |
| `RestartSec`     | `5`                            | Waits 5 seconds after a crash before restarting to prevent rapid restart loops. |
| `KillSignal`     | `SIGINT`                       | Sends `SIGINT` on stop — the signal Reverb expects for a graceful shutdown. |
| `TimeoutStopSec` | `30`                           | Allows 30 seconds for graceful shutdown before the process is force-killed. |
| `StandardOutput` | `journal`                      | Routes stdout to the systemd journal (viewable via `journalctl`).       |
| `StandardError`  | `journal`                      | Routes stderr to the systemd journal.                                   |

#### `[Install]`

| Directive   | Value                | Purpose                                                                 |
|-------------|----------------------|-------------------------------------------------------------------------|
| `WantedBy`  | `multi-user.target`  | Enables the service to start automatically when the system reaches multi-user mode (after boot). |

---

## Service Lifecycle

### Start the Service

```bash
sudo systemctl start reverb
```

### Stop the Service

```bash
sudo systemctl stop reverb
```

### Restart the Service

```bash
sudo systemctl restart reverb
```

### Check Service Status

```bash
systemctl status reverb
```

### View Logs (Live)

```bash
journalctl -u reverb -f
```

### Enable on Boot

```bash
sudo systemctl enable reverb
```

This ensures Reverb starts automatically after every server reboot.

### Reload systemd Daemon

After modifying the service file:

```bash
sudo systemctl daemon-reload
```

You must run this whenever `/etc/systemd/system/reverb.service` is edited.

---

## Deployment Checklist

Whenever a deployment includes changes that affect Reverb:

1. **Deploy code** — Pull the latest code to the production server.
2. **Run migrations if needed** — Execute any pending database migrations.
3. **Run optimize commands** — Clear and rebuild caches.
4. **Restart Reverb** — Apply the new code.

### Example

```bash
# Step 1: Pull latest code
git pull origin main

# Step 2: Run migrations (if applicable)
php artisan migrate --force

# Step 3: Optimize
php artisan optimize

# Step 4: Restart Reverb
sudo systemctl restart reverb
```

---

## Troubleshooting

### Verify Service Status

```bash
systemctl status reverb
```

Look for `active (running)` in the output. If the status shows `failed` or `inactive`, check the logs.

### Check Logs

```bash
journalctl -u reverb -f
```

The `-f` flag follows the log in real time. Remove it to view historical entries.

### Verify the Process is Running

```bash
ps aux | grep reverb
```

Expected output should show a `php8.4 artisan reverb:start` process.

### Check Listening Port

```bash
ss -ltnp | grep 8081
```

This confirms Reverb is bound to port **8081** and accepting connections.

### Useful Commands Summary

| Command                         | Purpose                                      |
|---------------------------------|----------------------------------------------|
| `systemctl status reverb`       | Check if the service is running and healthy. |
| `journalctl -u reverb -f`       | Stream live logs from the Reverb service.    |
| `ps aux \| grep reverb`         | Verify the process is active in the process table. |
| `ss -ltnp \| grep 8081`         | Confirm Reverb is listening on port 8081.    |
| `sudo systemctl restart reverb` | Restart the service (quick fix for many issues). |

---

## Important Notes

### Multi-PHP Environment

This server hosts **multiple PHP applications** with different PHP version requirements:

| Application   | PHP Version |
|---------------|-------------|
| WordPress     | PHP 8.3     |
| Laravel Backend | PHP 8.4   |

The service file **explicitly specifies PHP 8.4**:

```
ExecStart=/usr/bin/php8.4 artisan reverb:start --port=8081
```

> **CRITICAL:** Do NOT replace `/usr/bin/php8.4` with `/usr/bin/php`. The generic `php` symlink may resolve to PHP 8.3 (used by WordPress), which will cause compatibility issues and potential runtime errors.

---

## Architecture Decision Record

### Why systemd over PM2?

**Decision:** Reverb is managed by `systemd`, not PM2.

**Rationale:**

| Factor                        | systemd                                      | PM2                                          |
|-------------------------------|----------------------------------------------|----------------------------------------------|
| Service Management            | Native Linux service manager                 | Node.js process manager (extra dependency)   |
| Automatic Start on Boot       | Built-in via `systemctl enable`              | Requires `pm2 startup` (generates a crontab entry) |
| Automatic Restart on Crash    | Built-in via `Restart=always`                | Built-in via `pm2 restart`                   |
| Operational Complexity        | Low — standard Linux tooling                 | Medium — additional tool to install, configure, and monitor |
| Single Source of Truth        | Yes — service definition in `/etc/systemd/system/` | No — requires coordinating pm2.json, startup scripts, and cron |
| Log Management                | Centralized via `journalctl`                 | Separate log files (unless using pm2-logrotate) |
| Dependency on Node.js         | None                                         | Yes — PM2 requires a Node.js installation    |

**Conclusion:** systemd provides a simpler, more maintainable approach. It is the standard way to manage long-running services on Linux, requires no additional runtime dependencies, and integrates natively with Ubuntu's boot and logging infrastructure. Using PM2 would introduce an unnecessary Node.js dependency and add a layer of indirection for a process that is entirely PHP-based.

---

*Last updated: July 2026*
