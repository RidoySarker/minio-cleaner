FROM php:8.3-cli

RUN apt-get update && apt-get install -y \
    cron \
    curl \
    tzdata \
    && rm -rf /var/lib/apt/lists/*

RUN ln -sf /usr/share/zoneinfo/Asia/Dhaka /etc/localtime && dpkg-reconfigure -f noninteractive tzdata

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

COPY . /var/www/html/

WORKDIR /var/www/html

RUN mkdir -p /var/www/html && touch /var/www/html/cron.log && chmod -R 777 /var/www/html

RUN chmod +x /var/www/html/index.php

RUN composer install -vvv --no-interaction --optimize-autoloader

RUN touch /var/log/cron.log && chmod 666 /var/log/cron.log

RUN echo "30 9 * * * cd /var/www/html && /usr/local/bin/php index.php >> /var/www/html/cron.log 2>&1" > /etc/cron.d/my-cron-job

RUN chmod 0644 /etc/cron.d/my-cron-job

RUN crontab /etc/cron.d/my-cron-job

RUN chmod -R 755 /var/www/html

CMD cron && tail -f /var/log/cron.log
