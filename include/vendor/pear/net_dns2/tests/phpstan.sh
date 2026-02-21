#!/bin/bash

phpstan analyse --memory-limit 2G --level=8 -c phpstan.neon
