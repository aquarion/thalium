FROM thalium_base
ARG user

USER root

RUN apt-get update \
  && apt-get install -y --no-install-recommends \
    supervisor

COPY docker/scheduler-app/horizon.supervisor.conf /etc/supervisor/conf.d/horizon.conf

COPY docker/scheduler-app/laravel-cron.supervisor.conf /etc/supervisor/conf.d/laravel-cron.conf

COPY docker/scheduler-app/horizon-worker.supervisor.conf /etc/supervisor/conf.d/horizon-worker.conf

COPY docker/scheduler-app/supervisord.conf /etc/supervisor/supervisord.conf

ENTRYPOINT ["/usr/bin/supervisord", "-n", "-c",  "/etc/supervisor/supervisord.conf"]
WORKDIR /etc/supervisor/conf.d/

# USER $user
