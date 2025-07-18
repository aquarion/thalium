FROM thalium_base
ARG docker_app_lock_dir
ARG environment

USER root

COPY docker/php-fpm/$environment/* /usr/local/etc/php-fpm.d/
RUN set -x \
  [ ! -d $docker_app_lock_dir ] \
  && mkdir -p "$docker_app_lock_dir" \
  && chmod 660 "$docker_app_lock_dir" \
  && chown www-data "$docker_app_lock_dir"
RUN sed -i "s#__DOCKER_APP_LOCK_DIR__#$docker_app_lock_dir#" /usr/local/etc/php-fpm.d/*

USER www-data
