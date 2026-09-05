FROM php:8.3.32-fpm-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
      git \
      unzip \
      vim \
      less \
      sudo \
      procps \
      iproute2 \
      iputils-ping \
      ca-certificates \
      default-mysql-client \
      libpng-dev \
      libjpeg62-turbo-dev \
      libwebp-dev \
      openssh-client \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install pdo_mysql mysqli gd \
    && rm -rf /var/lib/apt/lists/*

COPY --from=node:22-bookworm-slim /usr/local/bin/node /usr/local/bin/node
COPY --from=node:22-bookworm-slim /usr/local/lib/node_modules /usr/local/lib/node_modules

RUN ln -s ../lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
    && ln -s ../lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

RUN cp "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini" \
    && groupadd --gid 1000 dev \
    && useradd --uid 1000 --gid dev --create-home --shell /bin/bash dev

COPY .devcontainer/php-content.ini /usr/local/etc/php/conf.d/content.ini

# Grant passwordless sudo to dev user
RUN echo "dev ALL=(ALL) NOPASSWD:ALL" > /etc/sudoers.d/dev && \
    chmod 0440 /etc/sudoers.d/dev

WORKDIR /workspace

CMD ["php-fpm"]
