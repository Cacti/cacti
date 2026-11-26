# dispatch: cli/queue_worker.php lib/api_queue.php
run_probe tests/e2e/queue/run.sh '^(lib/api_queue\.php|cli/queue_(admin|worker)\.php|include/global_settings\.php|install/upgrades/1_3_0\.php|tests/e2e/queue/)' 18276
