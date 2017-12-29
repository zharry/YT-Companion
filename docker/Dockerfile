FROM php:fpm

RUN echo "deb http://ftp.us.debian.org/debian stretch main" >> /etc/apt/sources.list && apt-get update -y && apt-get upgrade -y && apt-get install build-essential libreadline7 ffmpeg python wget -y && docker-php-ext-install mysqli pdo pdo_mysql

EXPOSE 9000

CMD ["php-fpm"]