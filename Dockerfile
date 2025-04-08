FROM php:8.3-fpm-alpine

ARG RUN_TESTS=true
ARG REMOVE_TEST_FILES=true

WORKDIR /app

COPY composer.json /app/
COPY tests/ /app/tests/
COPY phpunit.xml /app/
COPY src/validator.php /app/src/validator.php
COPY nginx.conf /etc/nginx/nginx.conf

RUN apk add --no-cache \
    openjdk11-jre \
    curl \
    nginx \
    tini \
    && curl -L -o /app/css-validator.jar https://github.com/w3c/css-validator/releases/latest/download/css-validator.jar \
    && curl -L https://getcomposer.org/download/2.7.6/composer.phar --output /usr/local/bin/composer \
    && chmod +x /usr/local/bin/composer \
    # Configure PHP-FPM to write logs to a file for debugging
    && echo "[global]" > /usr/local/etc/php-fpm.d/zzz-custom.conf \
        && echo "error_log = /var/log/php-fpm.log" >> /usr/local/etc/php-fpm.d/zzz-custom.conf \
        && echo "[www]" >> /usr/local/etc/php-fpm.d/zzz-custom.conf \
        && echo "catch_workers_output = yes" >> /usr/local/etc/php-fpm.d/zzz-custom.conf \
    # Create directories for logs and adjust permissions
    && mkdir -p /var/log/nginx \
        && touch /var/log/nginx/error.log \
        && chown -R nobody:nobody /var/log/nginx \
        && touch /var/log/php-fpm.log \
        && chown -R nobody:nobody /var/log/php-fpm.log \
    && rm -rf /etc/nginx/conf.d/* \
        /etc/nginx/nginx.conf.default \
    # Adjust app directory permissions
    && chown -R www-data:www-data /app \
        && find /app -type d -exec chmod 755 {} \; \
        && find /app -type f -exec chmod 644 {} \; \
    # Install dependencies and run tests if RUN_TESTS is true (local build only)
    && if [ "$RUN_TESTS" = "true" ]; then \
        composer install --no-interaction \
        && ./vendor/bin/phpunit --configuration phpunit.xml; \
    fi \
    # Install production dependencies
    && composer install --no-dev --optimize-autoloader \
    # Remove unnecessary files only if REMOVE_TEST_FILES is true
    && if [ "$REMOVE_TEST_FILES" = "true" ]; then \
        rm -rf composer.json composer.lock /usr/local/bin/composer \
            tests/ phpunit.xml vendor/bin/phpunit; \
    fi \
    && apk del --no-cache curl \
    && rm -rf /tmp/* \
    && rm -rf /var/cache/apk/*

EXPOSE 8080

ENTRYPOINT ["/sbin/tini", "--"]

CMD ["sh", "-c", "php-fpm -D && nginx -g 'daemon off;'"]