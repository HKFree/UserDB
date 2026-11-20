---
name: UserDB konfigurace databázového serveru
description: Konfigurace serveru dodaná zadavatelem 20. 11. 2026.
metadata:
  type: reference
---

# UserDB: konfigurace databázového serveru

Zadavatel 20. 11. 2026 potvrdil následující konfiguraci jako nastavení serveru. V předchozím zadání uvedl produkční MariaDB 10.11.19. Běhové hodnoty nebyly ověřeny připojením k serveru.

```ini
[mysqld]
bind-address = 0.0.0.0
max_connections = 300
max_allowed_packet = 128M
thread_stack = 192K
thread_cache_size = 8
open_files_limit = 4086
table_open_cache = 1024
table_definition_cache = 1536
key_buffer_size = 128M
query_cache_limit = 1M
query_cache_size = 128M
innodb_buffer_pool_size = 2G
join_buffer_size = 64M
tmp_table_size = 128M
max_heap_table_size = 128M
server-id = 1
log_bin = /var/log/mysql/mysql-bin.log
expire_logs_days = 10
max_binlog_size = 100M
binlog_do_db = userdb
log_bin_trust_function_creators = 1


sql_mode=ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION
```
