for fixture in SpikekillMutationMatrix SpikekillFloatDebug; do
	if [[ -f "tests/fixtures/Core/$fixture.php" ]]; then
		run_probe "$fixture.php" "$fixture"
	fi
done
