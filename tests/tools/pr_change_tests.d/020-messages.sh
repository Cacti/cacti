for fixture in DeviceCollectorMessages ReportMessageDelivery ExternalLinkMessage MessageFormatting TrustedActionMessages; do
	if [[ -f "tests/fixtures/Core/$fixture.php" ]]; then
		run_probe "$fixture.php" "$fixture"
	fi
done
