FROM php:8.3-cli

RUN apt-get -y update
RUN apt-get -y upgrade
RUN apt-get -y install git zip curl

RUN curl -fsSL https://deb.nodesource.com/setup_23.x -o nodesource_setup.sh
RUN bash nodesource_setup.sh
RUN apt-get install -y nodejs

WORKDIR /app

COPY --from=composer /usr/bin/composer /usr/bin/composer
COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/

RUN install-php-extensions zip
RUN git config --global --add safe.directory /app

RUN adduser dev

USER dev

ENTRYPOINT [ "sh", "entrypoint.sh" ]