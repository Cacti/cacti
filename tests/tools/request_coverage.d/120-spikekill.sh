# dispatch: lib/spikekill.php cli/removespikes.php spikekill.php
run_php_probe tests/fixtures/Core/SpikekillMutationMatrix.php '^(lib/spikekill\.php|tests/fixtures/Core/SpikekillMutationMatrix\.php)'
run_php_probe tests/fixtures/Core/RemoveSpikesHelp.php '^(cli/removespikes\.php|tests/fixtures/Core/RemoveSpikesHelp\.php)$'
run_php_probe tests/fixtures/Core/SpikekillRequestGate.php '^(spikekill\.php|tests/fixtures/Core/SpikekillRequestGate\.php)'
