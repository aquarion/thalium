FROM thalium_base

USER root

RUN apt-get update \
  && apt-get install -y --no-install-recommends \
    supervisor

COPY docker/horizon/horizon.conf /etc/supervisor/conf.d/horizon.conf
COPY docker/horizon/supervisord.conf /etc/supervisor/supervisord.conf


ENTRYPOINT ["/usr/bin/supervisord", "-n", "-c",  "/etc/supervisor/supervisord.conf"]
WORKDIR /etc/supervisor/conf.d/

USER $user
