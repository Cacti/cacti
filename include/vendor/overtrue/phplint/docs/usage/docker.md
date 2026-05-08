# Docker CLI

```shell
docker run --rm -t -v "${PWD}":/workdir overtrue/phplint:latest ./ --exclude=vendor --no-configuration --no-cache
```

> Please mount your source code to `/workdir` in the container.

**IMPORTANT** : Docker image with `latest` tag use the PHP 8.4 runtime !
