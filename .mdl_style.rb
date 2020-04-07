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
