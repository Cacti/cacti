if [[ -f tests/fixtures/Core/Boost/FailedDataSourceIsolation.php ]]; then
	run_probe Boost/FailedDataSourceIsolation.php boost-failed-data-source-isolation
fi
if [[ -f tests/fixtures/BoostBulkDrainFailureProbe.php ]]; then
	for scenario in middle boundary tail none; do
		run_probe ../BoostBulkDrainFailureProbe.php "boost-bulk-drain-$scenario" "$scenario" > /dev/null
	done
fi
if [[ -f tests/fixtures/BoostOnDemandFailureProbe.php ]]; then
	for scenario in first middle ok-substring archive-copy temp-create tail ack-create ack-insert ack-delete archive-ok ack-empty; do
		run_probe ../BoostOnDemandFailureProbe.php "boost-on-demand-$scenario" "$scenario" > /dev/null
	done
fi
if [[ -f tests/fixtures/BoostSourceExistenceProbe.php ]]; then
	for scenario in deleted query-failed; do
		run_probe ../BoostSourceExistenceProbe.php "boost-source-existence-$scenario" "$scenario" > /dev/null
	done
fi
