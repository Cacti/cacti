for fixture in GraphDrilldownMarkup HtmlGraphMarkup CardWidgetMarkup; do
	if [[ -f "tests/fixtures/Core/$fixture.php" ]]; then
		run_probe "$fixture.php" "$fixture"
	fi
done
add_browser_spec graph-drilldown
