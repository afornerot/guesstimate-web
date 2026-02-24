# Build guesstimate
FROM golang:alpine AS builder
RUN apk add --no-cache git
RUN go install github.com/bornholm/guesstimate/cmd/guesstimate@latest

FROM reg.cadoles.com/envole/nineapache:8.2

USER root

RUN apk add --no-cache tzdata mlocate
ENV TZ=Europe/Paris
RUN ln -sf /usr/share/zoneinfo/Europe/Paris /etc/localtime && echo "Europe/Paris" > /etc/timezone

# Installation de guesstimate
COPY --from=builder /go/bin/guesstimate /usr/local/bin/guesstimate

COPY ./misc/docker/apache.conf /etc/apache2/conf.d/nine/site.conf

# Définir le répertoire HOME de composer dans un endroit accessible
ENV COMPOSER_HOME=/tmp/composer
RUN mkdir -p /tmp/composer && chown -R apache:apache /tmp/composer

WORKDIR /app

# Crée vendor à l’avance et donne les droits
RUN mkdir -p /app/vendor && chown -R apache:apache /app

USER apache
COPY --chown=apache:apache . .

RUN composer install --no-interaction --optimize-autoloader

RUN mkdir -p /app/uploads \
    && mkdir -p /app/public/uploads/avatar \
    && mkdir -p /app/public/uploads/logo \
    && cp -rf /app/public/medias/logo /app/public/uploads/logo \
    && cp -rf /app/public/medias/avatar /app/public/uploads/avatar

CMD ["/app/misc/script/reconfigure.sh","/etc/apache2/apache2.sh"]
