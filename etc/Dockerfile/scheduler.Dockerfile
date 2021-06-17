FROM thalium

# RUN apt-get install supervisor
# COPY etc/horizon/supervisord.conf /etc/supervisor/conf.d/horizon.conf
# RUN supervisorctl reread
# RUN supervisorctl update
# RUN supervisorctl start horizon


COPY etc/horizon/scheduler.sh /usr/local/bin/scheduler.sh

#RUN chmod u+x /usr/local/bin/scheduler.sh

CMD ["/bin/bash", "/usr/local/bin/scheduler.sh"]