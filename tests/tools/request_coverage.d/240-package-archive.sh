# dispatch: lib/package.php package_import.php
run_php_probe tests/fixtures/Core/PackageArchive.php '^(lib/package\.php|tests/fixtures/Core/PackageArchive\.php)'
run_php_probe tests/fixtures/Core/PackageRepositoryWorkspace.php '^((package_import|lib/package)\.php|tests/fixtures/Core/PackageRepositoryWorkspace\.php)'
run_php_probe tests/fixtures/Core/PackageImportFileStatus.php '^(lib/(import|package)\.php|tests/fixtures/Core/PackageImportFileStatus\.php)'
run_php_probe tests/fixtures/Core/PackageDiffBoundary.php '^(package_import\.php|tests/fixtures/Core/PackageDiffBoundary\.php)'
