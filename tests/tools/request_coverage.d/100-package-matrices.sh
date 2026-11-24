# dispatch: package_repos.php lib/import.php
run_php_probe tests/fixtures/Core/PackageManifestMatrix.php '^(package_import\.php|package_repos\.php|lib/import\.php|tests/fixtures/Core/PackageManifestMatrix\.php)'
run_php_probe tests/fixtures/Core/PackageSaveMatrix.php '^(package_repos\.php|tests/fixtures/Core/PackageSaveMatrix\.php)'
