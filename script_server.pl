chdir("./scripts");
$loaded = 0;
while ($file = <*.pl>) {
	next if $file eq $0;
	eval 'require $file';
	$loaded++;
}
print "Loading complete ($loaded files loaded)\r\n";
while ($in = <STDIN>) {
	if ($in eq "quit\r\n") {
		print "Exiting\r\n";
		exit;
	} elsif ($in =~ /^([^\s]+) (.+)\r\n$/) {
		eval {
			$out = &{$1}($2);
		};
		print "$out\r\n";
	}
}
