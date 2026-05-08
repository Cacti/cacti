# GitLab CI

```yaml
code-quality:lint-php:
    image: overtrue/phplint:latest
    variables:
        INPUT_PATH: "./"
        INPUT_OPTIONS: "-c .phplint.yml"
    script: echo '' #prevents ci yml parse error
```
