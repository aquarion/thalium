FROM thalium_base
ARG user

USER root

COPY docker/php /usr/local/etc/php-fpm.d/
RUN sed -i "s/__USER__/$user/" /usr/local/etc/php-fpm.d/*

USER $user
