ARG DATABASE_IMAGE=mariadb:11.8
FROM ${DATABASE_IMAGE}

COPY cacti.sql /docker-entrypoint-initdb.d/cacti.sql.source
COPY docker/mysql-init.sh /docker-entrypoint-initdb.d/10-cacti-schema.sh

# The official MySQL and MariaDB entrypoints source non-executable shell
# initializers, which makes their docker_process_sql helper available.
RUN chmod 0644 /docker-entrypoint-initdb.d/10-cacti-schema.sh \
    /docker-entrypoint-initdb.d/cacti.sql.source
