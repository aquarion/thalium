FROM thalium_base
ARG user
ARG docker_app_lock_dir
ARG environment

USER root

COPY docker/php-fpm/$environment/* /usr/local/etc/php-fpm.d/
RUN mkdir -p "$docker_app_lock_dir"
RUN chmod 660 "$docker_app_lock_dir"
RUN chown $user "$docker_app_lock_dir"
RUN sed -i "s#__DOCKER_APP_LOCK_DIR__#$docker_app_lock_dir#" /usr/local/etc/php-fpm.d/*

USER $user
