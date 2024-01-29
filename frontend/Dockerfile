FROM php
COPY ./src/ .

RUN docker-php-ext-install mysqli
RUN docker-php-ext-enable mysqli

EXPOSE 80