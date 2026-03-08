FROM php:8.2-apache

# システムパッケージのインストール
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# SQLite用ライブラリのインストール
RUN apt-get update && apt-get install -y libsqlite3-dev && apt-get clean && rm -rf /var/lib/apt/lists/*

# PHP拡張機能のインストール
RUN docker-php-ext-install \
    pdo_sqlite \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    zip

# Apache mod_rewrite と SSL を有効化
RUN a2enmod rewrite ssl

# 自己署名証明書の生成
RUN openssl req -x509 -nodes -days 3650 -newkey rsa:2048 \
    -keyout /etc/ssl/private/selfsigned.key \
    -out /etc/ssl/certs/selfsigned.crt \
    -subj "/CN=localhost"

# Composerのインストール
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# PHPの設定ファイルをコピー
COPY ./docker/apache/php.ini /usr/local/etc/php/php.ini

# 作業ディレクトリの設定
WORKDIR /var/www/html

# SQLiteデータディレクトリの作成
RUN mkdir -p /var/www/data && chown -R www-data:www-data /var/www/data

# Apacheの設定
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80 443
