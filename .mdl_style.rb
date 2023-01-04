# +-------------------------------------------------------------------------+
# | Copyright (C) 2004-2023 The Cacti Group                                 |
# |                                                                         |
# | This program is free software; you can redistribute it and/or           |
# | modify it under the terms of the GNU General Public License             |
# | as published by the Free Software Foundation; either version 2          |
# | of the License, or (at your option) any later version.                  |
# |                                                                         |
# | This program is distributed in the hope that it will be useful,         |
# | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
# | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
# | GNU General Public License for more details.                            |
# +-------------------------------------------------------------------------+
# | Cacti: The Complete RRDtool-based Graphing Solution                     |
# +-------------------------------------------------------------------------+
# | This code is designed, written, and maintained by the Cacti Group. See  |
# | about.php and/or the AUTHORS file for specific developer information.   |
# +-------------------------------------------------------------------------+
# | http://www.cacti.net/                                                   |
# +-------------------------------------------------------------------------+

# customize style guide
all
rule "MD010", code_blocks: false
rule "MD013", code_blocks: false, tables: false
rule "MD029", style: "ordered"
rule "MD046", style: "fenced"

# Lesser rules
exclude_rule "MD010" # hard tabs
#exclude_rule "MD013" # line length

# Rule Exclusions
exclude_rule "MD001" # Headers are useful in other ways
exclude_rule "MD024" # Headers with same name are useful, but break link labeling (Rework needed on affected files before enabling this rule)
exclude_rule "MD041" # YAML header is being flagged as not the first
exclude_rule "MD046" # seems broken
