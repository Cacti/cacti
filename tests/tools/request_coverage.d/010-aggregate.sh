# dispatch: aggregate_graphs.php automation_aggregates.php
run_probe tests/e2e/aggregate-item-overrides.sh '^(aggregate_(graphs|templates)\.php|lib/api_aggregate\.php|install/upgrades/1_3_0\.php|tests/e2e/aggregate-item-overrides)' 18273
CACTI_AGGREGATE_TEST_BROWSER=1 run_probe tests/e2e/aggregate-automation.sh '^(automation_aggregates\.php|lib/(api_automation|automation|api_aggregate)\.php|install/upgrades/1_3_0\.php|tests/e2e/aggregate-automation)' 18274
