#!/usr/bin/env bash

SCRIPT_DIR=$( cd -- "$( dirname -- "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )

ASSETS_IMAGE_DIR="docs/assets"

php $SCRIPT_DIR/graph-uml/build.php cache $ASSETS_IMAGE_DIR
php $SCRIPT_DIR/graph-uml/build.php config $ASSETS_IMAGE_DIR
php $SCRIPT_DIR/graph-uml/build.php console $ASSETS_IMAGE_DIR
php $SCRIPT_DIR/graph-uml/build.php event $ASSETS_IMAGE_DIR
php $SCRIPT_DIR/graph-uml/build.php extension $ASSETS_IMAGE_DIR
php $SCRIPT_DIR/graph-uml/build.php finder $ASSETS_IMAGE_DIR
php $SCRIPT_DIR/graph-uml/build.php helper $ASSETS_IMAGE_DIR
php $SCRIPT_DIR/graph-uml/build.php linter $ASSETS_IMAGE_DIR
php $SCRIPT_DIR/graph-uml/build.php output $ASSETS_IMAGE_DIR
php $SCRIPT_DIR/graph-uml/build.php process $ASSETS_IMAGE_DIR
