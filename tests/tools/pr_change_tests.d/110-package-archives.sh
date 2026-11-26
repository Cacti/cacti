for fixture in PackageArchive PackageDiffBoundary PackageRepositoryWorkspace PackageImportFileStatus; do
	run_probe "$fixture.php" "$fixture"
done
