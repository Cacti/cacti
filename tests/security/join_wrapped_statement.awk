# Joins a wrapped call onto its matched line for the sink and hotspot
# inventories.
#
# Input and output are ripgrep `file:line:text` records. When the matched line
# leaves parentheses open, the following source lines are appended (leading
# whitespace collapsed to one space) until the parentheses balance. Without
# this, wrapping `exec("..." .` over several lines records only the first line,
# so a later edit that drops escaping from a continuation line would leave the
# baseline unchanged. Single-line matches are emitted unchanged.
#
# Parentheses inside quoted strings and after `//` or `#` comments are ignored.
# Heredocs and block comments are not parsed, which only over- or under-joins a
# record; MAX_JOIN bounds the damage.

BEGIN {
	MAX_JOIN = 60
}

# Returns the parenthesis depth change for one line of PHP source.
function paren_delta(text,    i, n, c, quote, delta) {
	n     = length(text)
	quote = ""
	delta = 0

	for (i = 1; i <= n; i++) {
		c = substr(text, i, 1)

		if (quote != "") {
			if (c == "\\") {
				i++
			} else if (c == quote) {
				quote = ""
			}
		} else if (c == "'" || c == "\"") {
			quote = c
		} else if (c == "#" || (c == "/" && substr(text, i + 1, 1) == "/")) {
			break
		} else if (c == "(") {
			delta++
		} else if (c == ")") {
			delta--
		}
	}

	return delta
}

{
	record = $0
	file   = record
	sub(/:.*/, "", file)
	rest   = substr(record, length(file) + 2)
	lineno = rest
	sub(/:.*/, "", lineno)
	text   = substr(rest, length(lineno) + 2)

	depth  = paren_delta(text)
	joined = text

	if (depth > 0) {
		# Skip to the matched line, then append continuation lines.
		current = 0

		while (current < lineno + 0 && (getline source < file) > 0) {
			current++
		}

		count = 0

		while (depth > 0 && count < MAX_JOIN && (getline source < file) > 0) {
			count++
			depth += paren_delta(source)
			sub(/^[ \t]+/, "", source)
			sub(/[ \t]+$/, "", source)
			joined = joined " " source
		}

		close(file)
	}

	print file ":" lineno ":" joined
}
